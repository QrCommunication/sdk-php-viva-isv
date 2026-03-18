# Viva Wallet ISV SDK for PHP

SDK PHP pour l'API Viva Wallet ISV Partner — comptes connectés, ordres ISV, composite auth, Cloud Terminal.

> **Ce SDK couvre les opérations ISV** (marketplace, comptes connectés, split payments). Pour les opérations marchands standard, voir `sdk-php-viva-merchant`.

## Installation

```bash
composer require qrcommunication/viva-isv-sdk
```

## Configuration

```php
use QrCommunication\VivaIsv\VivaIsvClient;

$isv = new VivaIsvClient(
    clientId: 'isv-client-id.apps.vivapayments.com',
    clientSecret: 'isv-client-secret',
    merchantId: 'isv-merchant-uuid',
    apiKey: 'isv-api-key',
    resellerId: 'reseller-uuid',
    resellerApiKey: 'reseller-api-key',
    environment: 'demo',
);
```

### Credentials ISV (3 types)

| Type | Clés | Usage |
|------|------|-------|
| **ISV OAuth** | `clientId` + `clientSecret` | Bearer token → New API |
| **ISV Merchant** | `merchantId` + `apiKey` | Basic Auth → Legacy API (propre compte) |
| **Reseller** | `resellerId` + `resellerApiKey` | Composite Basic Auth → Legacy API (marchands connectés) |

> **Composite Basic Auth** (non documenté par Viva) :
> Username = `{ResellerID}:{ConnectedMerchantID}`, Password = `{ResellerAPIKey}`

## Comptes Connectés

```php
// Créer un compte marchand connecté
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://app.com/onboarding/complete',
    partnerName: 'Ma Plateforme',
);
echo $account['accountId'];
echo $account['invitation']['redirectUrl']; // URL KYB onboarding

// Vérifier le statut
$info = $isv->accounts->get($accountId);
echo $info['verificationStatus']; // Pending, Verified, Rejected

// Raccourci
$isv->accounts->isVerified($accountId); // true/false

// URL d'onboarding (si pas encore vérifié)
$url = $isv->accounts->onboardingUrl($accountId);
```

## Ordres ISV (Smart Checkout)

```php
// Créer un ordre avec commission ISV
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,                        // €15.00
    isvAmount: 100,                      // €1.00 commission ISV
    customerDescription: 'Consultation',
    merchantReference: 'session_123',
    allowRecurring: true,
    preauth: false,
);

echo $order['checkout_url'];
// => https://demo.vivapayments.com/web/checkout?ref=1234567890
```

> **IMPORTANT** : `isvAmount` ne peut pas dépasser `amount`. Le SDK valide cela avant l'appel API.

## Transactions ISV (Composite Auth)

Toutes les opérations sur les transactions des marchands connectés utilisent le Composite Basic Auth automatiquement.

```php
// Détails d'une transaction
$txn = $isv->transactions->get('txn-uuid', 'merchant-uuid');

// Lister par date
$txns = $isv->transactions->listByDate('merchant-uuid', '2026-03-16');

// Capturer un preauth avec ISV fee
$isv->transactions->capture(
    transactionId: 'preauth-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);

// Paiement récurrent
$isv->transactions->recurring(
    initialTransactionId: 'initial-txn-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);

// Remboursement total
$isv->transactions->cancel('txn-uuid', 'merchant-uuid');

// Remboursement partiel
$isv->transactions->cancel('txn-uuid', 'merchant-uuid', amount: 500);
```

## POS Terminal (Cloud Terminal API ISV)

```php
// Rechercher les terminaux
$devices = $isv->terminals->search(merchantId: 'merchant-uuid');

// Envoyer une vente au terminal
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 100,                         // €1.00
    isvAmount: 10,                       // €0.10 commission
    terminalMerchantId: 'merchant-uuid',
    cashRegisterId: 'CR-01',
    merchantReference: 'sale_456',
);

echo $session['session_id']; // UUID de la session

// Polling manuel
$result = $isv->terminals->getSession($session['session_id']);
// success: false, eventId: 1100 = encore en cours

// Polling automatique (attend le résultat ou timeout)
$result = $isv->terminals->pollUntilComplete(
    sessionId: $session['session_id'],
    timeoutSeconds: 120,
    intervalMs: 3000,
);

if ($result['success']) {
    echo "Transaction: {$result['transactionId']}";
}

// Lister les sessions du jour
$sessions = $isv->terminals->listSessions('2026-03-16');

// Annuler une session en cours
$isv->terminals->abort($sessionId, cashRegisterId: 'CR-01');
```

### Event IDs (Cloud Terminal)

| eventId | Signification | Action |
|---------|--------------|--------|
| 0 | Succès | Transaction complétée |
| 1003 | Terminal timeout | Réessayer |
| 1006 | Refusée | Afficher erreur |
| 1016 | Annulée (abort) | Confirmer annulation |
| 1020 | Fonds insuffisants | Afficher message |
| 1099 | Erreur générique | Réessayer ou escalader |
| 1100 | En cours | Continuer à poller |
| 6000 | Paramètres incorrects | Corriger la requête |

> **Preauth ISV via Cloud Terminal n'est PAS supporté** par Viva. Le terminal retourne `eventId: 6000` "ISV preauth transactions are not supported". Utiliser Smart Checkout à la place.

## Webhooks

```php
// Vérification (GET)
$response = $isv->webhooks->verificationResponse('your-key');

// Parser un événement (POST)
$event = $isv->webhooks->parse($request->getContent());
// => ['event_type' => 'transaction.payment.created', 'event_data' => [...]]
```

## Enums Utiles

```php
use QrCommunication\VivaIsv\Enums\EcrEventId;
use QrCommunication\VivaIsv\Enums\TransactionEventId;

$event = EcrEventId::from(1100);
$event->shouldPoll();  // true
$event->isTerminal();  // false

$decline = TransactionEventId::from(10051);
$decline->label();      // 'Insufficient funds'
$decline->testAmount(); // 9951 (montant qui déclenche ce déclin en demo)
```

## Gestion des Erreurs

```php
use QrCommunication\VivaIsv\Exceptions\AuthenticationException;
use QrCommunication\VivaIsv\Exceptions\ApiException;

try {
    $order = $isv->orders->create(...);
} catch (AuthenticationException $e) {
    // Credentials ISV invalides
} catch (ApiException $e) {
    echo "Error [{$e->httpStatus}]: {$e->getMessage()}";
    echo "Viva error code: {$e->getErrorCode()}";
}
```

## Architecture

```
VivaIsvClient (point d'entrée)
├── accounts     → ConnectedAccounts  (New API, Bearer — /platforms/v1/)
├── orders       → IsvOrders          (New API, Bearer — /checkout/v2/isv/)
├── transactions → IsvTransactions    (Legacy API, Composite Basic Auth)
├── terminals    → EcrTerminals       (New API, Bearer — /ecr/isv/v1/)
└── webhooks     → Webhooks           (pas d'auth)
```

### Les 3 Auth du SDK

| Auth | Méthode | Quand |
|------|---------|-------|
| Bearer ISV OAuth | `HttpClient::post/get/delete` | Comptes, ordres, terminaux |
| Basic Auth ISV | `HttpClient::legacyPost/Get` | Propre compte Legacy API |
| Composite Basic Auth | `HttpClient::compositePost/Get/Delete` | Transactions marchands connectés |

## Pièges Connus

1. **Bearer token sur Legacy API** → 401. L'API legacy n'accepte QUE Basic Auth.
2. **ISV Basic Auth + transaction marchand** → "api action disabled". Il faut le Composite Auth.
3. **Connected merchant Basic Auth + IsvAmount** → `PaymentsRecurringIsvMissingReseller`.
4. **`isvAmount > amount`** → Rejeté par l'API.
5. **`scope=isv` dans le token** → `invalid_scope`. Ne pas envoyer de scope explicite.
6. **Preauth ISV Cloud Terminal** → `eventId: 6000`. Utiliser Smart Checkout.
7. **Capture preauth** nécessite "Allow recurring payments and pre-auth captures via API" activé.

## Carte de Test

| Champ | Valeur |
|-------|--------|
| Numéro | `4111111111111111` |
| CVV | `111` |
| Expiration | N'importe quelle date future |
| 3DS password | `Secret!33` |

## Montants de Test (Déclin)

| Cents | EventId | Description |
|-------|---------|-------------|
| 9951 | 10051 | Insufficient funds |
| 9954 | 10054 | Expired card |
| 9920 | 10200 | Stolen card |
| 9957 | 10057 | Card not permitted |
| 9961 | 10061 | Withdrawal limit |

## Licence

MIT
