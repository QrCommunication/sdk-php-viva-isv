# Viva Wallet ISV SDK — AI Instructions

> Ce fichier est automatiquement detecte par Claude Code, Cursor, Copilot et Codex.

## SDK Overview

Package PHP `qrcommunication/viva-isv-sdk` pour l'API Viva Wallet ISV Partner.
Gere les comptes connectes, les comptes ISV, les ordres ISV avec commission, le Composite Basic Auth, les terminaux POS Cloud, les transferts marketplace, le Native Checkout ISV et les webhooks ISV.

## Architecture

```
VivaIsvClient (point d'entree)
+-- accounts                  -> ConnectedAccounts       (New API, Bearer — /platforms/v1/)
+-- isvAccounts               -> IsvAccounts             (New API, Bearer — /isv/v1/)
+-- orders                    -> IsvOrders               (New API, Bearer — /checkout/v2/isv/)
+-- transactions              -> IsvTransactions         (Legacy API, Composite Basic Auth)
+-- terminals                 -> EcrTerminals            (New API, Bearer — /ecr/isv/v1/)
+-- transfers                 -> Transfers               (New API, Bearer — /platforms/v1/)
+-- marketplace               -> MarketplaceOrders       (New API, Bearer — /checkout/v2/orders/)
+-- nativeCheckout            -> NativeCheckoutIsv       (New API, Bearer — /nativecheckout/v2/isv/)
+-- isvWebhooks               -> IsvWebhooks             (New API, Bearer — /isv/v1/)
+-- webhooks                  -> Webhooks                (pas d'auth)
+-- messages                  -> IsvMessages             (Legacy API, Composite Basic Auth — /api/messages/config)
+-- sources                   -> IsvSources             (Legacy/Composite Basic Auth — /api/sources : create[ForMerchant], list[ForMerchant], find[ForMerchant], ensure[ForMerchant])
+-- resellers                 -> Resellers              (New API, Bearer — /resellers/v1/)
+-- merchantWebhookRegistrar() -> MerchantWebhookRegistrar (Helpers, lazy — wrapper idempotent de IsvMessages)
```

## Couverture des endpoints (v1.7.0)

| Domaine | Resource | Endpoints | Auth |
|---------|----------|-----------|------|
| Cloud Terminal refund/actions | `terminals` (`refund`, `createAction`, `getAction`) | `/ecr/isv/v1/transactions:refund`, `/ecr/isv/v1/actions[/{id}]` | Bearer |
| Retrieve/cancel order | `orders` (`retrieve`, `cancel`) | `/api/orders/{orderCode}` | Composite |
| Transaction filters | `transactions` (`listByClearanceDate`, `listByOrderCode`, `listBySourceCode`) | `/api/transactions?...` | Composite |
| MOTO charge | `transactions->moto()` | `POST /api/transactions` | Composite |
| Incremental preauth | `transactions->increasePreauth()` | `/acquiring/v1/isv/transactions/{id}:increasepreauth` | Bearer |
| Sources (own + connected merchant, idempotent) | `sources->{create,createForMerchant,list,listForMerchant,find,findForMerchant,ensure,ensureForMerchant}()` | `POST/GET /api/sources` | Legacy Basic / Composite |
| Resellers cash/bill | `resellers->*` | `/resellers/v1/...` | Bearer |
| Connected accounts | `accounts->{create,get,update,delete}` | `/platforms/v1/accounts` (update = **PATCH**) | Bearer |
| Transfers | `transfers->{send,reverse}` | `/platforms/v1/transfers` | Bearer |

## Les 3 Authentifications ISV

| Auth | Format | Quand |
|------|--------|-------|
| **Bearer ISV OAuth** | `Authorization: Bearer {token}` | Comptes connectes, comptes ISV, ordres ISV, terminaux POS, transferts, marketplace, native checkout, webhooks ISV |
| **Basic Auth ISV** | `MerchantID:APIKey` | Propre compte sur Legacy API |
| **Composite Basic Auth** | `ResellerID:ConnMerchantID` / `ResellerAPIKey` | Transactions marchands connectes (capture, recurring, cancel) |

### Composite Basic Auth (NON DOCUMENTE par Viva)

```
Username: {ResellerID}:{ConnectedMerchantID}   (deux UUIDs separes par :)
Password: {ResellerAPIKey}
```

Decouvert empiriquement — la documentation Viva ne specifie pas ce format.
Le SDK gere cela automatiquement via `HttpClient::compositePost/Get/DeleteUrl()`.

## Les 3 Hosts API

| Host | Auth | Params | Endpoints |
|------|------|--------|-----------|
| `accounts.vivapayments.com` | Form POST | — | `/connect/token` uniquement |
| `api.vivapayments.com` | Bearer token | **camelCase** | `/checkout/v2/isv/`, `/checkout/v2/orders/`, `/ecr/isv/v1/`, `/platforms/v1/`, `/isv/v1/`, `/nativecheckout/v2/isv/` |
| `www.vivapayments.com` | Basic Auth | **PascalCase** | `/api/orders`, `/api/transactions` |

**CRITIQUE** : ne jamais melanger les casses. Legacy = PascalCase, New = camelCase.

## Instanciation

```php
$isv = new VivaIsvClient(
    clientId: 'isv-xxx.apps.vivapayments.com',  // ISV OAuth
    clientSecret: 'secret',
    merchantId: 'isv-merchant-uuid',             // ISV own account
    apiKey: 'api-key',
    resellerId: 'reseller-uuid',                 // Composite Auth
    resellerApiKey: 'reseller-api-key',
    environment: 'demo',
);
```

## Patterns d'implementation

### Creer un compte connecte
```php
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://app.com/onboarding/complete',
    partnerName: 'Ma Plateforme',
);
// $account['accountId'], $account['invitation']['redirectUrl']
```

### Creer un compte ISV
```php
$account = $isv->isvAccounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://app.com/onboarding/complete',
    partnerName: 'Ma Plateforme',
    primaryColor: '#0052FF',
);
```

### Ordre ISV avec commission (Smart Checkout)
```php
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,        // 15,00 EUR total
    isvAmount: 100,      // 1,00 EUR commission ISV
    customerDescription: 'Consultation',
);
// NE PAS envoyer sourceCode — le connected merchant utilise sa source par defaut
// Endpoint : /checkout/v2/isv/orders?MerchantId={uuid}
```

### Capturer un preauth (Composite Auth)
```php
$isv->transactions->capture(
    transactionId: 'preauth-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);
// Legacy API POST /api/transactions/{id} avec Composite Basic Auth
// Params PascalCase : Amount, IsvAmount
```

### Paiement recurrent ISV
```php
$isv->transactions->recurring(
    initialTransactionId: 'initial-txn-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);
```

### Vente POS terminal
```php
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 100,
    isvAmount: 10,
    terminalMerchantId: 'merchant-uuid',
    cashRegisterId: 'CR-01',
);

// Polling automatique
$result = $isv->terminals->pollUntilComplete($session['session_id']);
```

### Abort session POS
```php
$isv->terminals->abort($sessionId, cashRegisterId: 'CR-01');
// Utilise GET (pas DELETE) avec cashRegisterId en query param
```

### Native Checkout ISV
```php
// 1. Generer un charge token
$token = $isv->nativeCheckout->createChargeToken(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    paymentData: $encryptedCardData,
);

// 2. Executer la transaction
$txn = $isv->nativeCheckout->createTransaction(
    connectedMerchantId: 'merchant-uuid',
    chargeToken: $token['chargeToken'],
    amount: 1500,
    isvAmount: 100,
);
```

### Gestion des webhooks ISV
```php
// Creer
$webhook = $isv->isvWebhooks->create(
    url: 'https://app.com/webhooks/viva',
    eventType: 'transaction.payment.created',
);

// Lister
$webhooks = $isv->isvWebhooks->list();

// Modifier
$isv->isvWebhooks->update('webhook-uuid', url: 'https://app.com/webhooks/v2');

// Supprimer
$isv->isvWebhooks->delete('webhook-uuid');
```

### Parsing des webhooks (21 evenements)
```php
$event = $isv->webhooks->parse($request->getContent());
// $event['event_type']    — ex. 'transaction.payment.created'
// $event['event_type_id'] — ex. 1796
// $event['event_data']    — donnees de l'evenement

// Verifier si un evenement est connu
Webhooks::isKnownEvent(1796); // true
Webhooks::EVENTS;             // tableau complet des 21 evenements
```

## Enums ISV

### EcrEventId (Cloud Terminal)
```php
EcrEventId::IN_PROGRESS->shouldPoll()  // true — continuer a poller
EcrEventId::SUCCESS->isSuccessful()    // true — transaction OK
EcrEventId::ABORTED->label()           // 'Transaction aborted'
```

| eventId | Enum | Signification |
|---------|------|---------------|
| 0 | SUCCESS | Transaction reussie |
| 1003 | TERMINAL_TIMEOUT | Terminal timeout |
| 1006 | DECLINED | Refusee par le serveur |
| 1016 | ABORTED | Annulee (abort reussi) |
| 1020 | INSUFFICIENT_FUNDS | Fonds insuffisants |
| 1099 | GENERIC_ERROR | Erreur generique |
| 1100 | IN_PROGRESS | En cours (poller) |
| 6000 | BAD_PARAMS | Parametres incorrects |

### TransactionEventId (raisons de declin)
```php
TransactionEventId::INSUFFICIENT_FUNDS->testAmount()  // 9951 (montant demo)
TransactionEventId::EXPIRED_CARD->label()              // 'Expired card'
```

## Exceptions

```
VivaException (RuntimeException)
+-- ApiException             -> Erreur HTTP (4xx/5xx)
+-- AuthenticationException  -> OAuth2 invalide (401)
```

## Pieges CRITIQUES (decouverts lors de la certification ISV)

1. **Bearer token sur Legacy API** -> 401. Legacy = Basic Auth uniquement.
2. **ISV Basic Auth sur transaction d'un marchand connecte** -> `"api action disabled"`. Il faut le Composite Auth.
3. **Connected merchant Basic Auth + IsvAmount** -> `PaymentsRecurringIsvMissingReseller`. Le contexte reseller est manquant.
4. **`isvAmount > amount`** -> Rejete. Le SDK valide cote client (InvalidArgumentException).
5. **`scope=isv` dans le token** -> `invalid_scope`. Ne PAS envoyer de scope explicite.
6. **Preauth ISV via Cloud Terminal** -> `eventId: 6000` "ISV preauth transactions are not supported". Utiliser Smart Checkout.
7. **Capture preauth** necessite "Allow recurring payments and pre-auth captures via API" active.
8. **Abort ECR** utilise GET (pas DELETE) avec `cashRegisterId` en query param.
9. **Ordres ISV** : NE PAS envoyer `sourceCode` — le connected merchant utilise sa source par defaut.

## Webhooks : deux niveaux distincts (CRITIQUE)

| Niveau | Resource | Endpoint | Auth | Events |
|--------|----------|----------|------|--------|
| **ISV-level** | `$isv->isvWebhooks` | `/isv/v1/webhooks` | Bearer | 1796/1797/1798/1799/8193/8194 — auto-broadcast par Viva |
| **Merchant-level** | `$isv->messages` | `/api/messages/config` | Composite | 768/769/2054 — doivent etre enregistres per-merchant |

**TOUJOURS utiliser `merchantWebhookRegistrar()` pour les merchant webhooks** (idempotent).
Ne JAMAIS utiliser `IsvMessages::register()` directement en provisioning — un doublon Viva
retourne HTTP 400 qui casserait le flux. `MerchantWebhookRegistrar::registerAll()` traite
les 400 duplicate comme succes silencieux.

```php
// OBLIGATOIRE (idempotent)
$isv->merchantWebhookRegistrar()->registerAll($merchantId, $callbackUrl);

// A EVITER en provisioning (non idempotent — 400 sur doublon)
$isv->messages->register($merchantId, 768, $callbackUrl);
```

## Helpers d'environnement

```php
$isv->getConfig()->isProduction(); // true en production, false en demo
$isv->getConfig()->isSandbox();    // true en demo, false en production
```

## Conventions de code

- PHP 8.2+ strict types
- Tous les montants en **centimes** (int)
- PSR-4 : `QrCommunication\VivaIsv\`
- Guzzle 7.8+ comme client HTTP
- Le `connectedMerchantId` est toujours un parametre explicite (pas de state global)
- Les helpers (`Helpers/`) encapsulent la logique de haut niveau au-dessus des Resources brutes

## Carte de test (demo)

- Numero : `4111111111111111`, CVV : `111`, 3DS : `Secret!33`
- Montants de declin : 9951 (insufficient), 9954 (expired), 9920 (stolen), 9957 (not permitted), 9961 (withdrawal limit)
