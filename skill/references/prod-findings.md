# Viva Wallet ISV — Findings prod-tested (2026-05-04)

> Reference compilée à partir de tests réels sur compte ISV production
> (Merchant ID `0119432c-543d-488e-8c22-ec821313759b` / Reseller `a9a47392-...`).
> Toutes les observations ci-dessous viennent d'appels HTTP exécutés en prod et
> documentés dans cette session de debug.

---

## ⚠️ Endpoints qui ne fonctionnent PAS comme la doc Viva l'annonce

### `GET /isv/v1/accounts` → HTTP 405

L'endpoint listing des comptes connectés sous l'ISV n'est **pas exposé** par
Viva. Le SDK `IsvAccounts::list()` jette une `ApiException` HTTP 405.

**Pas de workaround** : Viva ne fournit aucun moyen de lister les comptes via
API. Pour tracer les `accountId` créés, garder une trace côté application
(table `users`, `connected_accounts` settings, log).

```php
// ❌ Ne marche pas en prod
$accounts = $isv->isvAccounts->list();

// ✅ À la place : tracer côté app
$account = $isv->isvAccounts->create($email, $returnUrl);
DB::table('users')->where('id', $userId)->update([
    'viva_account_id' => $account['accountId'],
]);
```

### `DELETE /isv/v1/accounts/{id}` → HTTP 405

Pas d'endpoint pour supprimer un compte connecté. Les invitations orphelines
restent côté Viva indéfiniment. **Aucun cleanup possible via API.**

### `POST /isv/v1/accounts/{id}/cancel|disconnect` → HTTP 411

Tous les endpoints de "cancel" / "disconnect" testés retournent 411 Length
Required (Viva refuse de traiter). Conclusion : pas de désactivation possible
côté ISV.

### `/platforms/v1/accounts/*` (Marketplace API) → HTTP 403

Le SDK `ConnectedAccounts` pointe vers cette API (Marketplace), mais elle est
**désactivée par défaut sur les comptes ISV**. Toutes les méthodes
(`get`, `list`, `delete`, `update`, `onboardingUrl`, `isVerified`) retournent
403 / 405 chez nous.

**Workaround SDK-natif** : utiliser `IsvAccounts::get()` qui pointe vers
`/isv/v1/accounts/{id}` (l'API ISV propre) et expose le même payload :

```php
// ❌ Échoue en prod sur compte ISV
$account = $isv->accounts->get($accountId);  // /platforms/v1 → 403
$verified = $isv->accounts->isVerified($accountId);

// ✅ Marche en prod
$account = $isv->isvAccounts->get($accountId);  // /isv/v1 → 200
$verified = ($account['verified'] ?? false) === true;
$onboardingUrl = $account['invitation']['redirectUrl'] ?? null;
```

### `POST /api/messages/config` (merchant-level webhooks) → HTTP 404

L'endpoint pour enregistrer les webhooks merchant-level (events
**768/769/2054** — banking, settlements) **n'est plus exposé** chez Viva en
prod. Testé sur :

- `https://www.vivapayments.com/api/messages/config` → 404
- `https://api.vivapayments.com/api/messages/config` → 404
- `https://api.vivapayments.com/messages/config` → 404
- `/api/v2/messages/config`, `/api/v3/messages/config`, `/api/webhooks/config` → 404 partout

Le SDK `IsvMessages::register()` et `MerchantWebhookRegistrar::registerAll()`
échouent donc systématiquement avec un message
> `No HTTP resource was found that matches the request URI...`

**Workaround** : compenser via job de reconciliation périodique
(`viva:reconcile-settlements`) qui poll les settlements via l'endpoint
wallet API qui marche (voir section "Endpoints OK" ci-dessous).

### `POST /api/sources` (composite auth) → HTTP 400 (body vide)

Tentative de créer une Smart Checkout Source pour un connected merchant via
composite auth retourne 400 sans message. **Pas exposé côté ISV.**

**Important** : `IsvOrders::create()` documente explicitement
> "NO sourceCode (connected merchant uses default)"

Donc **pas besoin** de créer une Source — Viva en attribue une par défaut au
merchant connecté. L'order ISV l'utilise automatiquement.

### `GET /isv/v1/webhooks` → HTTP 405

Pas de listing des webhooks ISV-level enregistrés. Garder une trace côté
application des `EventTypeId` enregistrés.

---

## ✅ Endpoints qui marchent en prod (testés)

### Compte ISV propre (Basic Auth `merchantId:apiKey`)

```php
// Ces endpoints fonctionnent sur le compte ISV propre via Basic Auth simple
GET  /api/wallets                        → 200 (liste IBAN/wallets, soldes)
POST /api/orders                         → 200 (Smart Checkout direct)
GET  /api/orders/{orderCode}             → 200
POST /api/transactions/{transactionId}   → 200 (capture)
DELETE /api/transactions/{transactionId} → 200 (refund/void)
```

Ces endpoints sont appelables avec `Http::withBasicAuth($merchantId, $apiKey)`.
Le SDK les expose via `viva-merchant-sdk` (paquet séparé).

### Compte connecté (Composite Basic Auth)

```php
// Composite auth pour transactions sur connected merchants — marche en prod
POST /api/transactions/{id}              → 200 (capture preauth ISV)
DELETE /api/transactions/{id}            → 200 (cancel/refund avec isvAmount reversal)
POST /api/transactions/{initialTxnId}    → 200 (recurring charge)
```

### ISV Bearer (client_credentials grant)

```php
POST /connect/token                       → 200 (avec client_id ISV)
POST /isv/v1/accounts                     → 200 (créer invitation)
GET  /isv/v1/accounts/{id}                → 200 (état compte connecté)
POST /isv/v1/webhooks                     → 200 (events 1796-1799/8193/8194)
GET  /isv/v1/webhooks/token               → 200 (verification key handshake)
POST /checkout/v2/isv/orders?MerchantId=X → 200 (order ISV avec isvAmount)
GET  /ecr/isv/v1/...                      → 200 (terminaux POS)
```

---

## 🔑 OAuth — Split ISV vs Smart Checkout credentials

Les **ISV credentials** (scope `urn:viva:payments:core:api:isv`) supportent
**uniquement** le grant `client_credentials` (server-to-server). Tenter le
grant `authorization_code` avec ces credentials fait planter le portail
`accounts.vivapayments.com/connect/authorize` qui redirige vers
`/home/error?errorId=CfDJ8...` (errorId chiffré, non décodable).

Pour le flow OAuth Authorization Code (login utilisateur final pour connecter
un compte business existant), il faut des **credentials Smart Checkout
séparées** créées dans le portail Viva Banking → Settings → API Access →
Smart Checkout. Elles ont :

- Un `client_id` distinct (`xxxxx.apps.vivapayments.com`)
- Un `client_secret` distinct
- Des scopes restreints : `acquiring`, `acquiring:cardtokenization`,
  `acquiring:transactions`, `redirectcheckout` (PAS `isv` ni `merchantapi`)
- Un **redirect URI à whitelister manuellement** côté Viva (action support
  ou portail si l'option est exposée)

```php
// Credentials séparées dans la config
$isvClient = new VivaIsvClient(
    clientId: 'isv-xxx.apps.vivapayments.com',     // ISV client_credentials
    clientSecret: 'isv-secret',
    // ...
);

// Pour le OAuth Authorization Code, utiliser un autre client séparé
$oauthAuthUrl = "https://accounts.vivapayments.com/connect/authorize?"
    . http_build_query([
        'response_type' => 'code',
        'client_id' => 'sc-yyy.apps.vivapayments.com',  // Smart Checkout
        'redirect_uri' => 'https://app.example.com/callback',
        'scope' => 'urn:viva:payments:core:api:redirectcheckout',
        'state' => $csrfToken,
    ]);
```

**INTERDIT** : utiliser le client_id ISV pour le flow Authorization Code → la
page `/connect/authorize` rejette systématiquement (errorId chiffré).

---

## 🚨 Pages d'erreur Viva — diagnostic difficile

Toutes les erreurs OAuth passent par
`https://accounts.vivapayments.com/home/error?errorId=CfDJ8...`. Le `errorId`
est chiffré côté Viva via .NET DataProtection — **impossible à décoder côté
client**.

Causes possibles pour la même page d'erreur :
- `client_id` invalide
- `redirect_uri` non whitelisté
- `scope` non autorisé pour ce client
- Compte business existant en conflit de session navigateur

**Diagnostic** : tester en isolant chaque variable (différents client_id,
différents scopes, navigation privée pour éliminer conflit de session).

---

## 🎯 Création compte connecté — pièges UX

### Email d'invitation rattaché à un compte business existant

Si l'email passé à `POST /isv/v1/accounts` est **déjà associé à un compte
business Viva existant** (cas typique : owner Viva multi-business), l'écran
d'invitation Viva propose à l'utilisateur 2 options :

1. **"Sélectionner un compte existant"** (ex: "RL CONSEIL") → déclenche un
   flow OAuth Authorization Code en interne. **Plante avec
   "Failed to connect with PratiConnect"** si le redirect URI Smart Checkout
   n'est pas whitelisté côté Viva.

2. **"Create new business account"** → KYB direct, pas d'OAuth, fonctionne
   immédiatement. Crée un nouveau compte business chez Viva.

Si vous voulez **forcer le flow KYB direct** sans l'écran de sélection, passer
un email **non lié** à un compte business Viva (ex: alias `+tag` Gmail).

### `accountId` peut être attribué à un autre business email

Lors d'un test, j'ai créé une invitation pour `rony@ronylicha.net`. L'écran
de sélection a proposé de connecter à un compte business existant. Cliquer
"Continue" a échoué (page d'erreur) MAIS l'`accountId` côté Viva a été lié à
un autre email (`joelle@qrcommunication.com`) et un autre legalName
(`PHOENIX CONSULTING COMPANY`) — pas le compte ciblé.

**Conséquence** : l'API `GET /isv/v1/accounts/{id}` peut renvoyer un email et
un legalName qui **ne correspondent pas à l'email d'invitation**. Toujours
vérifier `merchantId` et `legalName` après KYB pour valider la bonne entité.

### Idempotence du POST /isv/v1/accounts

Tenter `POST /isv/v1/accounts` avec le même email plusieurs fois crée
**plusieurs accountIds distincts** (pas de dedup côté Viva). Garder une
référence de l'`accountId` actif côté application pour éviter de polluer
Viva avec des invitations orphelines.

---

## 🔄 Webhooks — comportement réel

### ISV-level webhooks (1796/1797/1798/1799/8193/8194)

✅ Enregistrement via `POST /isv/v1/webhooks` fonctionne (un appel par event).

⚠️ **Verification handshake** : avant le premier `POST /isv/v1/webhooks`,
appeler `GET /isv/v1/webhooks/token` qui renvoie une `verification_key`. Cette
clé doit être :

1. Stockée côté application (utilisée pour valider la signature HMAC des
   webhooks entrants).
2. Renvoyée par votre endpoint `GET /api/webhooks/viva` (ou équivalent) avec
   payload `{"Key": "..."}`. Sans cela, l'enregistrement de webhook
   ultérieur peut planter.

### Merchant-level webhooks (768/769/2054)

❌ L'endpoint `/api/messages/config` retourne 404 partout (cf. section plus
haut). **Workaround** : reconciliation horaire via cron qui poll les
settlements via wallet API.

### Signature HMAC entrante

Webhooks Viva incluent un header `X-Viva-Signature` = HMAC-SHA256 du body
brut signé avec la `webhook_verification_key` obtenue via
`GET /isv/v1/webhooks/token`.

```php
$expected = hash_hmac('sha256', $rawPayload, $verificationKey);
if (! hash_equals($expected, $request->header('X-Viva-Signature'))) {
    return response()->json(['error' => 'Invalid signature'], 401);
}
```

⚠️ Viva fait des appels périodiques (au moins toutes les heures) sur l'URL
webhook **sans X-Viva-Signature** depuis IPs Azure (51.138.x.x, 20.54.x.x).
Si vous renvoyez 4xx, Viva considère le webhook comme cassé. **Renvoyer 200
sur les calls sans signature** ou logger en warning sans bloquer.

---

## 💳 Smart Checkout — Sources

Le SDK ISV ne propose volontairement **aucune méthode pour créer une
Smart Checkout Source** côté connected merchant car :

1. Viva attribue automatiquement une Source par défaut à chaque connected
   merchant lors du KYB
2. `IsvOrders::create()` n'a pas besoin de `sourceCode` — utilise la default
3. Les success/failure URLs sont configurables uniquement côté merchant
   (portail Viva Banking → Sources → Edit)

**INTERDIT** : tenter `POST /api/sources` avec composite auth → HTTP 400
silencieux, n'a jamais marché en prod ISV.

---

## 📊 Workflow recommandé post-KYB

Après que `verified=true` arrive sur un connected merchant (webhook 8194 ou
sync via `IsvAccounts::get()`), le provisioning automatique côté ISV doit :

1. **Stocker** `merchantId`, `legalName`, `acquiringEnabled` côté app
2. **Tenter** `MerchantWebhookRegistrar::registerAll()` (idempotent — sera
   probablement `failed` à cause du 404, c'est OK)
3. **Activer** les fonctionnalités banking côté UI (transactions, settlements,
   transfers SEPA, IBAN, cards, wallets, POS)
4. **Notifier** le merchant que son compte est prêt

Le SDK est **résilient** : aucun appel ne bloque si l'endpoint Viva est
indisponible — toutes les méthodes critiques renvoient un état clair.

---

## 📝 Diff config Viva nécessaire vs SDK

Quand vous démarrez une intégration ISV, demandez à Viva :

| Item | À demander à Viva |
|------|-------------------|
| Activation rôle ISV | "Activate ISV Partner Program on merchant {ID}" |
| Smart Checkout app séparée | "Create Smart Checkout OAuth app for our platform" |
| Whitelist redirect URI | "Add `https://app.example.com/callback` to redirect URIs of OAuth app {client_id}" |
| `/api/messages/config` activation | "Enable merchant-level webhook registration on our ISV (events 768/769/2054)" — souvent refusé/non dispo |
| `/platforms/v1/*` activation | "Enable Marketplace API on our ISV" — souvent refusé pour ISV pure |
| Scope `biservices:merchantapi` | "Add merchantapi scope to our ISV credentials" — optionnel |

---

## 📚 Sources

- Tests prod 2026-05-04 sur PratiConnect avec compte ISV
  `0119432c-543d-488e-8c22-ec821313759b`
- Connected merchant test : `8d00bedf-2b41-4671-9532-06dd9cc27a16` (RL CONSEIL)
- Doc Viva crawled : `/home/rony/doc-crawler/viva-docs-old/` (418 pages)
- SDK officiel : `qrcommunication/viva-isv-sdk` v1.5.0
