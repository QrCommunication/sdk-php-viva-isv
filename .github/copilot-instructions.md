# Viva Wallet ISV SDK — AI Instructions

> Ce fichier est automatiquement detecte par Claude Code, Cursor, Copilot et Codex.

## SDK Overview

Package PHP `qrcommunication/viva-isv-sdk` pour l'API Viva Wallet ISV Partner.
Gere les comptes connectes, les comptes ISV, les ordres ISV avec commission, le Composite Basic Auth, les terminaux POS Cloud, les transferts marketplace, le Native Checkout ISV et les webhooks ISV.

## Architecture

```
VivaIsvClient (point d'entree)
+-- accounts       -> ConnectedAccounts  (New API, Bearer — /platforms/v1/)
+-- isvAccounts    -> IsvAccounts        (New API, Bearer — /isv/v1/)
+-- orders         -> IsvOrders          (New API, Bearer — /checkout/v2/isv/)
+-- transactions   -> IsvTransactions    (Legacy API, Composite Basic Auth)
+-- terminals      -> EcrTerminals       (New API, Bearer — /ecr/isv/v1/)
+-- transfers      -> Transfers          (New API, Bearer — /platforms/v1/)
+-- marketplace    -> MarketplaceOrders  (New API, Bearer — /checkout/v2/orders/)
+-- nativeCheckout -> NativeCheckoutIsv  (New API, Bearer — /nativecheckout/v2/isv/)
+-- isvWebhooks    -> IsvWebhooks        (New API, Bearer — /isv/v1/)
+-- webhooks       -> Webhooks           (pas d'auth)
```

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
    amount: 1500,
    isvAmount: 100,
    customerDescription: 'Consultation',
);
// NE PAS envoyer sourceCode
```

### Capturer un preauth (Composite Auth)
```php
$isv->transactions->capture(
    transactionId: 'preauth-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);
```

### Native Checkout ISV
```php
$token = $isv->nativeCheckout->createChargeToken(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    paymentData: $encryptedCardData,
);

$txn = $isv->nativeCheckout->createTransaction(
    connectedMerchantId: 'merchant-uuid',
    chargeToken: $token['chargeToken'],
    amount: 1500,
    isvAmount: 100,
);
```

### Gestion webhooks ISV
```php
$webhook = $isv->isvWebhooks->create(
    url: 'https://app.com/webhooks/viva',
    eventType: 'transaction.payment.created',
);
$isv->isvWebhooks->list();
$isv->isvWebhooks->update('webhook-uuid', url: 'https://new-url.com');
$isv->isvWebhooks->delete('webhook-uuid');
```

### Parsing webhooks (21 evenements)
```php
$event = $isv->webhooks->parse($request->getContent());
// $event['event_type'], $event['event_type_id'], $event['event_data']
Webhooks::isKnownEvent(1796); // true
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
$result = $isv->terminals->pollUntilComplete($session['session_id']);
```

## Exceptions

```
VivaException (RuntimeException)
+-- ApiException             -> Erreur HTTP (4xx/5xx)
+-- AuthenticationException  -> OAuth2 invalide (401)
```

## Pieges CRITIQUES

1. **Bearer token sur Legacy API** -> 401
2. **ISV Basic Auth sur transaction marchand connecte** -> "api action disabled"
3. **Connected merchant Basic Auth + IsvAmount** -> PaymentsRecurringIsvMissingReseller
4. **isvAmount > amount** -> Rejete
5. **scope=isv** -> invalid_scope
6. **Preauth ISV via Cloud Terminal** -> eventId: 6000
7. **Capture preauth sans activation** -> Echec
8. **Abort ECR** utilise GET (pas DELETE)
9. **sourceCode dans ordres ISV** -> Rejet

## Carte de test (demo)

- Numero : `4111111111111111`, CVV : `111`, 3DS : `Secret!33`
- Montants de declin : 9951, 9954, 9920, 9957, 9961
