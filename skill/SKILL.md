---
name: sdk-viva-isv
description: Use when working with Viva Wallet ISV Partner, connected merchant accounts, marketplace payments, split payments (isvAmount), composite Basic Auth, Cloud Terminal POS, or projects importing qrcommunication/viva-isv-sdk (composer). Covers all ISV platform operations.
---

# SDK Viva Wallet ISV — Référence complète

SDK PHP pour toutes les opérations ISV Partner Viva Wallet : comptes connectés, ordres avec commission (isvAmount), capture/récurrent via Composite Basic Auth (non documenté par Viva), terminaux POS Cloud, transferts marketplace, Native Checkout ISV, webhooks ISV et parsing de 21 événements.

## Package

| Langage | Package | Repo |
|---------|---------|------|
| PHP 8.2+ | `qrcommunication/viva-isv-sdk` | `QrCommunication/sdk-php-viva-isv` |

## Installation

```bash
composer require qrcommunication/viva-isv-sdk
```

## Quick Start

```php
use QrCommunication\VivaIsv\VivaIsvClient;

$isv = new VivaIsvClient(
    clientId: 'isv-xxx.apps.vivapayments.com',  // ISV OAuth Client ID
    clientSecret: 'secret',                      // ISV OAuth Client Secret
    merchantId: 'isv-merchant-uuid',             // ISV Merchant ID
    apiKey: 'api-key',                           // ISV API Key
    resellerId: 'reseller-uuid',                 // Reseller ID (pour Composite Auth)
    resellerApiKey: 'reseller-api-key',           // Reseller API Key
    environment: 'demo',
);

// Le SDK gère les 3 authentifications automatiquement
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100, // commission ISV
);
echo $order['checkout_url'];
```

## Architecture — 10 Resources, 36+ méthodes

```
VivaIsvClient (point d'entrée, 3 auth modes, lazy)
├── accounts        → ConnectedAccounts   (/platforms/v1/accounts, Bearer)
│   ├── create(email, returnUrl, partnerName?, logoUrl?) → ['accountId' => string, 'invitation' => ['redirectUrl' => string]]
│   ├── get(accountId) → array
│   ├── list() → array
│   ├── isVerified(accountId) → bool
│   ├── onboardingUrl(accountId) → ?string
│   ├── update(accountId, attributes) → array
│   └── delete(accountId) → array
│
├── isvAccounts     → IsvAccounts         (/isv/v1/accounts, Bearer)
│   ├── create(email, returnUrl, partnerName?, primaryColor?, logoUrl?) → array
│   ├── get(accountId) → array
│   └── list() → array
│
├── orders          → IsvOrders           (/checkout/v2/isv/orders, Bearer)
│   ├── create(connectedMerchantId, amount, isvAmount?, customerDescription?, merchantReference?, allowRecurring?, preauth?)
│   │   → ['order_code' => int, 'checkout_url' => string]
│   │   ⚠ NE PAS envoyer sourceCode — le connected merchant utilise sa source par défaut
│   │   ⚠ isvAmount ne peut pas dépasser amount (validé côté SDK)
│   └── checkoutUrl(orderCode) → string
│
├── transactions    → IsvTransactions     (Legacy API, Composite Basic Auth)
│   ├── get(transactionId, connectedMerchantId) → array
│   ├── listByDate(connectedMerchantId, date) → array[]
│   ├── capture(transactionId, connectedMerchantId, amount, isvAmount?) → array
│   ├── recurring(initialTransactionId, connectedMerchantId, amount, isvAmount?, sourceCode?) → array
│   └── cancel(transactionId, connectedMerchantId, amount?, sourceCode?) → array
│   ⚠ Toutes les méthodes exigent connectedMerchantId — pas de state global
│   ⚠ Auth Composite = ResellerID:ConnMerchantID / ResellerAPIKey (géré par le SDK)
│
├── terminals       → EcrTerminals        (/ecr/isv/v1/, Bearer)
│   ├── search(merchantId?, statusId?, sourceCode?) → array[]
│   ├── sale(terminalId, amount, isvAmount, terminalMerchantId, cashRegisterId?, merchantReference?, currencyCode?, sessionId?)
│   │   → ['session_id' => string, 'success' => true]
│   ├── getSession(sessionId) → array (poll — checker eventId)
│   ├── listSessions(date) → array[]
│   ├── abort(sessionId, cashRegisterId) → array
│   └── pollUntilComplete(sessionId, timeoutSeconds?, intervalMs?) → array
│   ⚠ Preauth ISV via Cloud Terminal = PAS SUPPORTÉ (eventId 6000). Utiliser Smart Checkout.
│   ⚠ Abort utilise GET (pas DELETE) avec cashRegisterId en query param
│
├── transfers       → Transfers           (/platforms/v1/transfers, Bearer)
│   ├── send(targetAccountId, amount, sourceWalletId?, transactionId?, description?) → ['transferId' => string]
│   └── reverse(transferId, amount?) → ['transferId' => string]
│
├── marketplace     → MarketplaceOrders   (/checkout/v2/orders/, Bearer)
│   ├── create(amount, sellerAccountId, sellerAmount, customerDescription?, merchantReference?, sourceCode?, preauth?)
│   │   → ['order_code' => int, 'checkout_url' => string, 'platform_fee' => int]
│   └── cancel(transactionId, amount?, reverseTransfers?, refundPlatformFee?) → array
│
├── nativeCheckout  → NativeCheckoutIsv   (/nativecheckout/v2/isv/, Bearer)
│   ├── createChargeToken(connectedMerchantId, amount, paymentData, paymentMethodId?) → ['chargeToken' => string]
│   └── createTransaction(connectedMerchantId, chargeToken, amount, isvAmount?, currencyCode?, merchantTrns?, customerTrns?, preauth?) → array
│
├── isvWebhooks     → IsvWebhooks         (/isv/v1/webhooks, Bearer)
│   ├── create(url, eventType, description?) → array
│   ├── list() → array[]
│   ├── update(webhookId, url, eventType?) → array
│   └── delete(webhookId) → array
│
├── messages        → IsvMessages         (/api/messages/config, Composite Basic Auth)
│   ├── register(connectedMerchantId, eventTypeId, callbackUrl) → array{MessageId, EventTypeId, Url, IsActive}
│   │   ⚠ HTTP 400 si duplicate — utiliser merchantWebhookRegistrar() en provisioning
│   ├── list(connectedMerchantId) → array[]
│   └── delete(connectedMerchantId, messageId) → array
│
├── merchantWebhookRegistrar() → MerchantWebhookRegistrar  (lazy helper, Helpers/)
│   └── registerAll(connectedMerchantId, callbackUrl, events?) → array{event_id, status, message?}[]
│       ⚠ Idempotent : HTTP 400 duplicate → status 'already_exists' (pas d'exception)
│       ⚠ BANKING_EVENTS par défaut : 768 (Bank Transfer Created), 769 (Bank Transfer Executed), 2054 (Account Transaction Created)
│
└── webhooks        → Webhooks            (pas d'auth — parsing)
    ├── verificationResponse(verificationKey) → ['StatusCode' => 0, 'Key' => string]
    ├── parse(rawBody) → ['event_type' => string, 'event_type_id' => int, 'event_data' => array]
    ├── isKnownEvent(eventTypeId) → bool (static)
    └── EVENTS (const) → array<int, string> (21 événements)
```

## Webhooks : deux niveaux distincts (CRITIQUE)

| Niveau | Resource | Endpoint | Auth | Events | Notes |
|--------|----------|----------|------|--------|-------|
| **ISV-level** | `$isv->isvWebhooks` | `/isv/v1/webhooks` | Bearer | 1796/1797/1798/1799/8193/8194 | Auto-broadcast par Viva — à enregistrer une seule fois pour la plateforme |
| **Merchant-level** | `$isv->messages` | `/api/messages/config` | Composite | 768/769/2054 | À enregistrer per-merchant lors du provisioning |

### Règle : TOUJOURS `merchantWebhookRegistrar()` pour les merchant webhooks

```php
// CORRECT — idempotent, pas d'erreur si déjà enregistré
$results = $isv->merchantWebhookRegistrar()->registerAll(
    connectedMerchantId: $merchantId,
    callbackUrl: 'https://app.example.com/api/webhooks/viva',
);

// INCORRECT en provisioning — 400 sur doublon lève ApiException
$isv->messages->register($merchantId, 768, $callbackUrl);
```

## Les 3 Authentifications

Le SDK gère les 3 modes automatiquement — le développeur passe juste les 6 credentials au constructeur.

| Auth | Quand | Format interne |
|------|-------|----------------|
| **Bearer ISV OAuth** | Comptes, ordres ISV, terminaux, transferts, marketplace, native checkout, webhooks ISV | `Authorization: Bearer {token}` (lazy, auto-refresh) |
| **Basic Auth ISV** | Propre compte sur Legacy API | `MerchantID:APIKey` |
| **Composite Basic Auth** | Transactions des marchands connectés (capture, recurring, cancel) **+ merchant webhooks** (`IsvMessages`) | `ResellerID:ConnMerchantID` / `ResellerAPIKey` |

### Composite Basic Auth (NON DOCUMENTÉ par Viva)

```
Username: {ResellerID}:{ConnectedMerchantID}   (deux UUIDs séparés par :)
Password: {ResellerAPIKey}
```

Découvert empiriquement lors de la certification ISV. Le SDK construit ce format via `IsvConfig::compositeUsername()`.

## Les 3 Hosts API

Le SDK route automatiquement.

| Host prod | Host demo | Auth | Params |
|-----------|-----------|------|--------|
| `accounts.vivapayments.com` | `demo-accounts.vivapayments.com` | Form POST | Token OAuth uniquement |
| `api.vivapayments.com` | `demo-api.vivapayments.com` | Bearer | **camelCase** |
| `www.vivapayments.com` | `demo.vivapayments.com` | Basic/Composite | **PascalCase** |

## Montants

**TOUJOURS en centimes** (int). `1500` = 15,00 EUR. `isvAmount` = commission ISV prélevée automatiquement.

## Enums

### EcrEventId (Cloud Terminal)

```php
use QrCommunication\VivaIsv\Enums\EcrEventId;

EcrEventId::IN_PROGRESS->shouldPoll();   // true — continuer à poller
EcrEventId::SUCCESS->isSuccessful();     // true
EcrEventId::TERMINAL_TIMEOUT->isTerminal(); // true — arrêter de poller
```

| eventId | Enum | Signification | shouldPoll | isTerminal |
|---------|------|---------------|------------|------------|
| 0 | SUCCESS | Transaction réussie | false | true |
| 1003 | TERMINAL_TIMEOUT | Terminal timeout | false | true |
| 1006 | DECLINED | Refusée par le serveur | false | true |
| 1016 | ABORTED | Annulée (abort réussi) | false | true |
| 1020 | INSUFFICIENT_FUNDS | Fonds insuffisants | false | true |
| 1099 | GENERIC_ERROR | Erreur générique | false | true |
| 1100 | IN_PROGRESS | En cours | **true** | **false** |
| 6000 | BAD_PARAMS | Paramètres incorrects | false | true |

### TransactionEventId (raisons de déclin)

```php
use QrCommunication\VivaIsv\Enums\TransactionEventId;

TransactionEventId::INSUFFICIENT_FUNDS->label();      // 'Insufficient funds'
TransactionEventId::INSUFFICIENT_FUNDS->testAmount();  // 9951
```

| transactionEventId | Enum | testAmount (centimes) |
|--------------------|------|----------------------|
| 10051 | INSUFFICIENT_FUNDS | 9951 |
| 10054 | EXPIRED_CARD | 9954 |
| 10043 | STOLEN_CARD | 9920 |
| 10057 | NOT_PERMITTED_CARDHOLDER | 9957 |
| 10061 | WITHDRAWAL_LIMIT | 9961 |
| 10006 | GENERAL_ERROR | 9906 |
| 10014 | INVALID_CARD | 9914 |

## Exceptions

```
VivaException (RuntimeException)
├── ApiException              → Erreur HTTP 4xx/5xx
└── AuthenticationException   → OAuth2 invalide (401)

$e->httpStatus, $e->responseBody, $e->getErrorCode(), $e->getErrorText()
```

## 21 Webhooks

Identiques au SDK Merchant — voir `Webhooks::EVENTS` constant.

## Patterns d'implémentation courants

### Onboarding d'un marchand connecté
```php
// 1. Créer le compte
$account = $isv->accounts->create(
    email: 'praticien@example.com',
    returnUrl: 'https://app.com/settings?viva_onboarding=complete',
    partnerName: 'Ma Plateforme',
);
$accountId = $account['accountId'];
$onboardingUrl = $account['invitation']['redirectUrl'];
// 2. Rediriger vers $onboardingUrl pour le KYB
// 3. Vérifier plus tard
$isv->accounts->isVerified($accountId); // true quand KYB validé
```

### Paiement avec commission ISV
```php
$order = $isv->orders->create(
    connectedMerchantId: $merchant->viva_merchant_id,
    amount: 5000,      // 50€ total
    isvAmount: 500,     // 5€ commission plateforme
    customerDescription: 'Consultation ostéopathie',
);
// Le praticien reçoit 45€, la plateforme 5€
```

### Capture preauth avec ISV fee
```php
$isv->transactions->capture(
    transactionId: $preauthTxnId,
    connectedMerchantId: $merchantId,
    amount: 5000,
    isvAmount: 500,
);
// Utilise Composite Basic Auth automatiquement
```

### Vente POS terminal avec polling
```php
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 1500,
    isvAmount: 100,
    terminalMerchantId: $merchantId,
    cashRegisterId: 'CR-01',
);
// Polling automatique (bloquant, max 120s)
$result = $isv->terminals->pollUntilComplete($session['session_id']);
if ($result['success']) {
    echo "Transaction: {$result['transactionId']}";
}
```

### Marketplace avec split automatique
```php
$order = $isv->marketplace->create(
    amount: 10000,                // 100€ total
    sellerAccountId: $sellerId,
    sellerAmount: 8500,           // 85€ au vendeur
    customerDescription: 'Achat marketplace',
);
// platform_fee = 15€ (calculé automatiquement)
```

## 9 Pièges de certification ISV

| # | Piège | Le SDK gère |
|---|-------|-------------|
| 1 | Bearer token sur Legacy API → 401 | ✅ Routing automatique |
| 2 | ISV Basic Auth sur transaction marchand → "api action disabled" | ✅ Composite Auth auto |
| 3 | Connected merchant Auth + IsvAmount → PaymentsRecurringIsvMissingReseller | ✅ Composite Auth auto |
| 4 | isvAmount > amount → rejeté | ✅ Validation côté SDK |
| 5 | scope=isv dans le token → invalid_scope | ✅ Jamais envoyé |
| 6 | Preauth ISV via Cloud Terminal → eventId 6000 | ❌ Non supporté par Viva |
| 7 | Capture sans "Allow recurring" activé | ❌ Config manuelle Viva |
| 8 | Abort ECR utilise GET pas DELETE | ✅ Le SDK fait GET |
| 9 | sourceCode dans ordres ISV → ignoré | ✅ Jamais envoyé |

## Carte de test (demo)

| Champ | Valeur |
|-------|--------|
| Numéro | `4111111111111111` |
| CVV | `111` |
| Expiration | N'importe quelle date future |
| 3DS password | `Secret!33` |

## Structure du projet

```
src/
├── VivaIsvClient.php            (point d'entrée, 10 resources)
├── IsvConfig.php                 (6 credentials + compositeUsername())
├── HttpClient.php                (Guzzle, 3 auth modes)
├── Resources/
│   ├── ConnectedAccounts.php     (create, get, list, isVerified, onboardingUrl, update, delete)
│   ├── IsvAccounts.php           (create, get, list)
│   ├── IsvOrders.php             (create, checkoutUrl)
│   ├── IsvTransactions.php       (get, listByDate, capture, recurring, cancel)
│   ├── EcrTerminals.php          (search, sale, getSession, listSessions, abort, pollUntilComplete)
│   ├── Transfers.php             (send, reverse)
│   ├── MarketplaceOrders.php     (create, cancel)
│   ├── NativeCheckoutIsv.php     (createChargeToken, createTransaction)
│   ├── IsvWebhooks.php           (create, list, update, delete)
│   └── Webhooks.php              (verificationResponse, parse, isKnownEvent, EVENTS)
├── Enums/
│   ├── Environment.php           (DEMO, PRODUCTION + URLs)
│   ├── EcrEventId.php            (8 valeurs + shouldPoll/isTerminal/isSuccessful)
│   └── TransactionEventId.php    (24 valeurs + label/testAmount)
└── Exceptions/
    ├── VivaException.php         (base)
    ├── ApiException.php          (4xx/5xx)
    └── AuthenticationException.php (401)
```

---

## ⚠️ Production gotchas (testé sur prod ISV 2026-05)

Ces comportements ont été observés sur un compte ISV Viva production réel
et ne sont **pas** explicitement documentés par Viva. Lire avant intégration.

### Endpoints qui ne marchent PAS

| Endpoint | Status | Workaround |
|----------|--------|------------|
| `GET /isv/v1/accounts` (`isvAccounts->list()`) | 405 | Tracer accountIds app-side |
| `DELETE /isv/v1/accounts/{id}` | 405 | Pas de cleanup possible |
| `GET /isv/v1/webhooks` (`isvWebhooks->list()`) | 405 | Tracer EventTypeIds app-side |
| `/platforms/v1/accounts/*` (Marketplace) | 403 | Utiliser `isvAccounts` à la place |
| `POST /api/messages/config` (merchant webhooks) | 404 | Polling reconciliation |
| `POST /api/sources` (composite auth) | 400 | Pas besoin (Viva utilise default) |

### Endpoints de remplacement à utiliser

```php
// ❌ Ces appels ConnectedAccounts → 403 sur ISV pure
$isv->accounts->onboardingUrl($accountId);
$isv->accounts->isVerified($accountId);

// ✅ Equivalents IsvAccounts qui marchent
$isv->isvAccounts->getOnboardingUrl($accountId);   // since v1.6
$isv->isvAccounts->isVerified($accountId);         // since v1.6
$isv->isvAccounts->isAcquiringEnabled($accountId); // since v1.6
```

### OAuth Authorization Code — credentials séparées

Les credentials ISV (`urn:viva:payments:core:api:isv` scope) supportent
**uniquement** `client_credentials`. Pour le flow Authorization Code (login
utilisateur final pour connecter un compte business existant), il faut des
credentials **Smart Checkout** distinctes :

- Créer dans portail Viva → Settings → API Access → Smart Checkout
- Scopes : `acquiring`, `acquiring:transactions`, `redirectcheckout`
- Whitelister manuellement le `redirect_uri` côté Viva (action support)

Tenter `/connect/authorize` avec des credentials ISV redirige toujours vers
`accounts.vivapayments.com/home/error?errorId=CfDJ8...` (errorId chiffré
.NET DataProtection — non décodable).

### Smart Checkout Sources

`IsvOrders::create()` n'accepte volontairement **pas** de `sourceCode` :
> "NO sourceCode (connected merchant uses default)"

Viva attribue automatiquement une Source par défaut à chaque connected
merchant. Les success/failure URLs se configurent uniquement côté merchant
(portail Viva → Sources → Edit). **Ne JAMAIS tenter de créer une Source via
API** côté ISV (`POST /api/sources` → 400 silencieux).

### Webhooks merchant-level (`MerchantWebhookRegistrar`)

Le helper retourne désormais 4 statuts (depuis v1.6) :
- `created` — succès
- `already_exists` — duplicate (idempotent)
- `endpoint_unavailable` — HTTP 404 sur `/api/messages/config` (cas typique
  prod ISV — endpoint déprécié/restreint)
- `failed` — autre erreur (auth, réseau, 5xx)

```php
$results = $isv->merchantWebhookRegistrar()->registerAll(
    connectedMerchantId: $merchantId,
    callbackUrl: 'https://app.example.com/api/webhooks/viva',
);

if (MerchantWebhookRegistrar::hasEndpointIssue($results)) {
    // L'API webhooks merchant-level n'est pas dispo sur ce compte ISV.
    // Compenser par un job de reconciliation périodique.
}
```

### Email d'invitation rattaché à un compte business existant

Si l'email passé à `isvAccounts->create()` est **déjà associé à un compte
business Viva**, l'écran d'invitation propose 2 options :

1. **Sélectionner un compte existant** → déclenche OAuth Authorization Code
   en interne. Plante avec "Failed to connect with PartnerName" si redirect
   URI Smart Checkout n'est pas whitelisté côté Viva.
2. **Create new business account** → KYB direct. Marche immédiatement.

Pour forcer le KYB direct sans écran de sélection, utiliser un email
**non lié** à Viva (alias `+tag` Gmail par exemple).

### `accountId` peut être lié à un autre business email

Cas observé en prod : invitation pour `userA@example.com` → écran de
sélection → Continue → erreur OAuth → l'`accountId` côté Viva finit lié à
`userB@otherdomain.com` (autre business du même tenant Viva), avec un
`legalName` qui ne correspond pas. **Toujours vérifier `merchantId` et
`legalName` après KYB avant de considérer l'onboarding réussi.**

### Verification handshake

Avant le premier `IsvWebhooks::create()`, appeler
`IsvWebhooks::verificationToken()` pour récupérer la verification key. Cette
clé doit être :

1. Stockée app-side (utilisée pour signer les webhooks entrants HMAC-SHA256)
2. Renvoyée par votre endpoint `GET /api/webhooks/viva` avec
   `{"Key": "<verification-key>"}`. Sans cela, l'enregistrement ultérieur
   peut être refusé par Viva.

### Pings sans signature de Viva

Viva fait des appels périodiques (au moins toutes les heures) sur l'URL
webhook **sans `X-Viva-Signature`** depuis IPs Azure (`51.138.x.x`,
`20.54.x.x`). Si vous renvoyez 4xx, Viva considère le webhook comme cassé.
**Renvoyer 200 sur les calls sans signature** ou logger en warning sans
bloquer.

---

## 📋 Configuration support à demander à Viva

Au démarrage d'une intégration ISV, ouvrir un ticket Viva avec :

| Item | À demander |
|------|------------|
| Activation rôle ISV | "Activate ISV Partner Program on merchant {ID}" |
| Smart Checkout app | "Create Smart Checkout OAuth app for our platform" |
| Whitelist redirect URI | "Add `https://app.example.com/callback` to redirect URIs of OAuth app `{client_id}`" |
| `/api/messages/config` activation | "Enable merchant-level webhook registration on our ISV (events 768/769/2054)" — souvent refusé |
| `/platforms/v1/*` activation | "Enable Marketplace API on our ISV" — souvent refusé |
| Scope `biservices:merchantapi` | "Add merchantapi scope to our ISV credentials" — optionnel |

Voir aussi `references/prod-findings.md` pour le détail complet des
endpoints testés et leurs résultats.
