# Viva Wallet ISV SDK — AI Instructions

> Ce fichier est automatiquement détecté par Claude Code, Cursor, Copilot et Codex.

## SDK Overview

Package PHP `qrcommunication/viva-isv-sdk` pour l'API Viva Wallet ISV Partner.
Gère les comptes connectés, les ordres ISV avec commission, le Composite Basic Auth et les terminaux POS Cloud.

## Architecture

```
VivaIsvClient (point d'entrée)
├── accounts     → ConnectedAccounts  (New API, Bearer — /platforms/v1/)
├── orders       → IsvOrders          (New API, Bearer — /checkout/v2/isv/)
├── transactions → IsvTransactions    (Legacy API, Composite Basic Auth)
├── terminals    → EcrTerminals       (New API, Bearer — /ecr/isv/v1/)
└── webhooks     → Webhooks           (pas d'auth)
```

## Les 3 Authentifications ISV

| Auth | Format | Quand |
|------|--------|-------|
| **Bearer ISV OAuth** | `Authorization: Bearer {token}` | Comptes connectés, ordres ISV, terminaux POS |
| **Basic Auth ISV** | `MerchantID:APIKey` | Propre compte sur Legacy API |
| **Composite Basic Auth** | `ResellerID:ConnMerchantID` / `ResellerAPIKey` | Transactions marchands connectés (capture, recurring, cancel) |

### Composite Basic Auth (NON DOCUMENTÉ par Viva)

```
Username: {ResellerID}:{ConnectedMerchantID}   (deux UUIDs séparés par :)
Password: {ResellerAPIKey}
```

Découvert empiriquement — la documentation Viva ne spécifie pas ce format.
Le SDK gère cela automatiquement via `HttpClient::compositePost/Get/DeleteUrl()`.

## Les 3 Hosts API

| Host | Auth | Params | Endpoints |
|------|------|--------|-----------|
| `accounts.vivapayments.com` | Form POST | — | `/connect/token` uniquement |
| `api.vivapayments.com` | Bearer token | **camelCase** | `/checkout/v2/isv/`, `/ecr/isv/v1/`, `/platforms/v1/` |
| `www.vivapayments.com` | Basic Auth | **PascalCase** | `/api/orders`, `/api/transactions` |

**CRITIQUE** : ne jamais mélanger les casses. Legacy = PascalCase, New = camelCase.

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

## Patterns d'implémentation

### Créer un compte connecté
```php
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://app.com/onboarding/complete',
    partnerName: 'Ma Plateforme',
);
// $account['accountId'], $account['invitation']['redirectUrl']
```

### Ordre ISV avec commission (Smart Checkout)
```php
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,        // €15.00 total
    isvAmount: 100,      // €1.00 commission ISV
    customerDescription: 'Consultation',
);
// NE PAS envoyer sourceCode — le connected merchant utilise sa source par défaut
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

### Paiement récurrent ISV
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

## Enums ISV

### EcrEventId (Cloud Terminal)
```php
EcrEventId::IN_PROGRESS->shouldPoll()  // true — continuer à poller
EcrEventId::SUCCESS->isSuccessful()    // true — transaction OK
EcrEventId::ABORTED->label()           // 'Transaction aborted'
```

| eventId | Enum | Signification |
|---------|------|---------------|
| 0 | SUCCESS | Transaction réussie |
| 1003 | TERMINAL_TIMEOUT | Terminal timeout |
| 1006 | DECLINED | Refusée par le serveur |
| 1016 | ABORTED | Annulée (abort réussi) |
| 1020 | INSUFFICIENT_FUNDS | Fonds insuffisants |
| 1099 | GENERIC_ERROR | Erreur générique |
| 1100 | IN_PROGRESS | En cours (poller) |
| 6000 | BAD_PARAMS | Paramètres incorrects |

### TransactionEventId (raisons de déclin)
```php
TransactionEventId::INSUFFICIENT_FUNDS->testAmount()  // 9951 (montant demo)
TransactionEventId::EXPIRED_CARD->label()              // 'Expired card'
```

## Exceptions

```
VivaException (RuntimeException)
├── ApiException             → Erreur HTTP (4xx/5xx)
└── AuthenticationException  → OAuth2 invalide (401)
```

## Pièges CRITIQUES (découverts lors de la certification ISV)

1. **Bearer token sur Legacy API** → 401. Legacy = Basic Auth uniquement.
2. **ISV Basic Auth sur transaction d'un marchand connecté** → `"api action disabled"`. Il faut le Composite Auth.
3. **Connected merchant Basic Auth + IsvAmount** → `PaymentsRecurringIsvMissingReseller`. Le contexte reseller est manquant.
4. **`isvAmount > amount`** → Rejeté. Le SDK valide côté client (InvalidArgumentException).
5. **`scope=isv` dans le token** → `invalid_scope`. Ne PAS envoyer de scope explicite.
6. **Preauth ISV via Cloud Terminal** → `eventId: 6000` "ISV preauth transactions are not supported". Utiliser Smart Checkout.
7. **Capture preauth** nécessite "Allow recurring payments and pre-auth captures via API" activé.
8. **Abort ECR** utilise GET (pas DELETE) avec `cashRegisterId` en query param.
9. **Ordres ISV** : NE PAS envoyer `sourceCode` — le connected merchant utilise sa source par défaut.

## Conventions de code

- PHP 8.2+ strict types
- Tous les montants en **centimes** (int)
- PSR-4 : `QrCommunication\VivaIsv\`
- Guzzle 7.8+ comme client HTTP
- Le `connectedMerchantId` est toujours un paramètre explicite (pas de state global)

## Carte de test (demo)

- Numéro : `4111111111111111`, CVV : `111`, 3DS : `Secret!33`
- Montants de déclin : 9951 (insufficient), 9954 (expired), 9920 (stolen), 9957 (not permitted), 9961 (withdrawal limit)
