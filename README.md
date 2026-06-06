# SDK PHP Viva Wallet ISV

[![Version 1.7.1](https://img.shields.io/badge/version-1.7.1-blue.svg)](https://github.com/qrcommunication/sdk-php-viva-isv/releases)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--isv--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-isv-sdk)

SDK PHP complet pour l'API Viva Wallet ISV Partner. 11 ressources couvrant : comptes connectes, comptes ISV, ordres ISV avec commission, transactions (capture, recurrent, annulation), terminaux Cloud POS, transferts marketplace, ordres marketplace, Native Checkout ISV, webhooks ISV (CRUD), webhooks merchant-level (CRUD) et parsing (21 evenements).

> **Ce SDK couvre les operations ISV (comptes connectes, composite auth, commission).** Pour les operations marchands standard, voir [`sdk-php-viva-merchant`](https://github.com/qrcommunication/sdk-php-viva-merchant).

---

## Table des matieres

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Reference des ressources](#reference-des-ressources)
  - [1. ConnectedAccounts — comptes connectes](#1-connectedaccounts--comptes-connectes)
  - [2. IsvAccounts — comptes ISV](#2-isvaccounts--comptes-isv)
  - [3. IsvOrders — ordres Smart Checkout ISV](#3-isvorders--ordres-smart-checkout-isv)
  - [4. IsvTransactions — transactions marchands connectes](#4-isvtransactions--transactions-marchands-connectes)
  - [5. EcrTerminals — terminaux POS Cloud](#5-ecrterminals--terminaux-pos-cloud)
  - [6. Transfers — transferts marketplace](#6-transfers--transferts-marketplace)
  - [7. MarketplaceOrders — ordres marketplace](#7-marketplaceorders--ordres-marketplace)
  - [8. NativeCheckoutIsv — paiement natif ISV](#8-nativecheckoutisv--paiement-natif-isv)
  - [9. IsvWebhooks — CRUD webhooks ISV](#9-isvwebhooks--crud-webhooks-isv)
  - [10. Webhooks — verification et parsing](#10-webhooks--verification-et-parsing)
  - [11. IsvMessages — webhooks merchant-level](#11-isvmessages--webhooks-merchant-level)
- [Merchant-Level Webhook Registration](#merchant-level-webhook-registration)
- [IsvConfig — helpers d'environnement](#isvconfig--helpers-denvironnement)
- [Architecture](#architecture)
- [Authentification](#authentification)
- [Enums](#enums)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Webhooks (21 evenements)](#webhooks-21-evenements)
- [Pieges evites par le SDK](#pieges-evites-par-le-sdk)
- [Test en sandbox](#test-en-sandbox)
- [Documentation API interactive](#documentation-api-interactive)
- [Integration AI](#integration-ai-claude-cursor-copilot-codex)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-isv-sdk
```

**Prerequis** : PHP 8.2+ avec `ext-json` et `ext-curl`.

---

## Quick Start

```php
use QrCommunication\VivaIsv\VivaIsvClient;

// 1. Instancier le client avec les 6 credentials
$isv = new VivaIsvClient(
    clientId:       'isv-client-id.apps.vivapayments.com',  // ISV OAuth2
    clientSecret:   'isv-client-secret',                    // ISV OAuth2
    merchantId:     'isv-merchant-uuid',                    // ISV Basic Auth
    apiKey:         'isv-api-key',                          // ISV Basic Auth
    resellerId:     'reseller-uuid',                        // Composite Auth
    resellerApiKey: 'reseller-api-key',                     // Composite Auth
    environment:    'demo',                                 // 'demo' ou 'production'
);

// 2. Creer un compte marchand connecte
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://myapp.com/onboarding/complete',
);
// => ['accountId' => 'uuid', 'invitation' => ['redirectUrl' => 'https://...']]

// 3. Creer un ordre avec commission ISV
$order = $isv->orders->create(
    connectedMerchantId: $account['accountId'],
    amount: 1500,       // 15,00 EUR
    isvAmount: 100,     // 1,00 EUR de commission
);
// => ['order_code' => 1234567890, 'checkout_url' => 'https://...']

// 4. Rediriger le client vers le checkout
header('Location: ' . $order['checkout_url']);

// 5. Capturer une pre-autorisation
$isv->transactions->capture('preauth-txn-uuid', 'merchant-uuid', amount: 1500);

// 6. Vente sur terminal POS
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 1500,
    isvAmount: 100,
    terminalMerchantId: 'merchant-uuid',
);
$result = $isv->terminals->pollUntilComplete($session['session_id']);

// 7. Rembourser
$isv->transactions->cancel('txn-uuid', 'merchant-uuid', amount: 500);
```

### Ou trouver les credentials

| Credential     | Emplacement dans le Dashboard Viva                              |
|----------------|------------------------------------------------------------------|
| Client ID      | Settings > API Access > ISV OAuth Credentials > Client ID       |
| Client Secret  | Settings > API Access > ISV OAuth Credentials > Client Secret   |
| Merchant ID    | Settings > API Access > Merchant ID                             |
| API Key        | Settings > API Access > API Key                                 |
| Reseller ID    | Settings > API Access > Reseller Credentials > Reseller ID      |
| Reseller API Key | Settings > API Access > Reseller Credentials > Reseller API Key |

### Test de connexion

```php
if ($isv->testConnection()) {
    echo 'Connexion ISV OK';
}
```

---

## Reference des ressources

### 1. ConnectedAccounts — comptes connectes

**Propriete** : `$isv->accounts`

Gestion des comptes marchands connectes a la plateforme ISV. Creation, consultation, KYB onboarding, verification, mise a jour et suppression.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `create` | `create(string $email, string $returnUrl, ?string $partnerName, ?string $logoUrl)` | `array{accountId, invitation}` |
| `get` | `get(string $accountId)` | `array` (details du compte) |
| `list` | `list()` | `array` (liste paginee) |
| `isVerified` | `isVerified(string $accountId)` | `bool` |
| `onboardingUrl` | `onboardingUrl(string $accountId)` | `?string` |
| `update` | `update(string $accountId, array $attributes)` | `array` |
| `delete` | `delete(string $accountId)` | `array` |

```php
// Creer un compte connecte avec branding
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://myapp.com/onboarding/complete',
    partnerName: 'Ma Plateforme',
    logoUrl: 'https://myapp.com/logo.png',
);

// Rediriger le marchand vers l'onboarding KYB
header('Location: ' . $account['invitation']['redirectUrl']);

// Verifier le statut KYB
if ($isv->accounts->isVerified($account['accountId'])) {
    echo 'Le marchand peut recevoir des paiements';
}

// Recuperer l'URL d'onboarding (null si deja verifie)
$url = $isv->accounts->onboardingUrl($account['accountId']);

// Lister tous les comptes connectes
$accounts = $isv->accounts->list();

// Mettre a jour
$isv->accounts->update($account['accountId'], ['email' => 'new@example.com']);

// Supprimer
$isv->accounts->delete($account['accountId']);
```

---

### 2. IsvAccounts — comptes ISV

**Propriete** : `$isv->isvAccounts`

Comptes ISV via le namespace `/isv/v1/` avec options de branding personnalise (couleur, logo).

| Methode | Signature | Retour |
|---------|-----------|--------|
| `create` | `create(string $email, string $returnUrl, ?string $partnerName, ?string $primaryColor, ?string $logoUrl)` | `array{accountId, invitation}` |
| `get` | `get(string $accountId)` | `array` |
| `list` | `list()` | `array` |

```php
$account = $isv->isvAccounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://myapp.com/onboarding/done',
    partnerName: 'Ma Plateforme',
    primaryColor: '#0052FF',
    logoUrl: 'https://myapp.com/logo.png',
);

$details = $isv->isvAccounts->get($account['accountId']);
$all = $isv->isvAccounts->list();
```

---

### 3. IsvOrders — ordres Smart Checkout ISV

**Propriete** : `$isv->orders`

Creation d'ordres de paiement Smart Checkout pour les marchands connectes avec commission ISV.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `create` | `create(string $connectedMerchantId, int $amount, int $isvAmount, ?string $customerDescription, ?string $merchantReference, bool $allowRecurring, bool $preauth)` | `array{order_code, checkout_url}` |
| `checkoutUrl` | `checkoutUrl(int $orderCode)` | `string` |

```php
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,                    // 15,00 EUR
    isvAmount: 100,                  // 1,00 EUR de commission
    customerDescription: 'Consultation bien-etre',
    merchantReference: 'INV-2026-001',
    allowRecurring: true,            // Tokeniser la carte
    preauth: false,
);

echo $order['order_code'];     // 1234567890
echo $order['checkout_url'];   // https://demo.vivapayments.com/web/checkout?ref=1234567890

// Reconstruire l'URL a partir d'un code
$url = $isv->orders->checkoutUrl(1234567890);
```

**Regles** :
- `isvAmount` doit etre <= `amount` (sinon `InvalidArgumentException`)
- Le marchand connecte utilise sa source de paiement par defaut (pas de `sourceCode`)

---

### 4. IsvTransactions — transactions marchands connectes

**Propriete** : `$isv->transactions`

Operations sur les transactions des marchands connectes. Le SDK utilise automatiquement le Composite Basic Auth.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `get` | `get(string $transactionId, string $connectedMerchantId)` | `array` |
| `listByDate` | `listByDate(string $connectedMerchantId, string $date)` | `array` |
| `capture` | `capture(string $transactionId, string $connectedMerchantId, int $amount, ?int $isvAmount)` | `array` |
| `recurring` | `recurring(string $initialTransactionId, string $connectedMerchantId, int $amount, ?int $isvAmount, ?string $sourceCode)` | `array` |
| `cancel` | `cancel(string $transactionId, string $connectedMerchantId, ?int $amount, ?string $sourceCode)` | `array` |

```php
// Consulter une transaction
$txn = $isv->transactions->get('txn-uuid', 'merchant-uuid');

// Lister les transactions d'une journee
$transactions = $isv->transactions->listByDate('merchant-uuid', '2026-03-18');

// Capturer une pre-autorisation
$isv->transactions->capture(
    transactionId: 'preauth-txn-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);

// Paiement recurrent
$isv->transactions->recurring(
    initialTransactionId: 'initial-txn-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
);

// Remboursement total
$isv->transactions->cancel('txn-uuid', 'merchant-uuid');

// Remboursement partiel (5,00 EUR)
$isv->transactions->cancel('txn-uuid', 'merchant-uuid', amount: 500);
```

**Prerequis** : l'option "Allow recurring payments and pre-auth captures via API" doit etre activee dans Settings > API Access du compte ISV.

---

### 5. EcrTerminals — terminaux POS Cloud

**Propriete** : `$isv->terminals`

Terminaux de paiement Cloud POS ISV. Recherche, vente, polling de sessions, abort.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `search` | `search(?string $merchantId, ?int $statusId, ?string $sourceCode)` | `array` |
| `sale` | `sale(int $terminalId, int $amount, int $isvAmount, string $terminalMerchantId, string $cashRegisterId, ?string $merchantReference, int $currencyCode, ?string $sessionId)` | `array{session_id, success}` |
| `getSession` | `getSession(string $sessionId)` | `array` |
| `listSessions` | `listSessions(string $date)` | `array` |
| `abort` | `abort(string $sessionId, string $cashRegisterId)` | `array` |
| `pollUntilComplete` | `pollUntilComplete(string $sessionId, int $timeoutSeconds, int $intervalMs)` | `array` |

```php
// Rechercher les terminaux d'un marchand
$terminals = $isv->terminals->search(merchantId: 'merchant-uuid');

// Vente POS ISV
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 1500,
    isvAmount: 100,
    terminalMerchantId: 'merchant-uuid',
    cashRegisterId: 'PratiConnect-CR1',
    merchantReference: 'INV-2026-001',
);

echo $session['session_id']; // UUID de la session

// Polling jusqu'au resultat (defaut: 120s timeout, 3s intervalle)
$result = $isv->terminals->pollUntilComplete($session['session_id']);

// Interpreter le resultat avec l'enum
use QrCommunication\VivaIsv\Enums\EcrEventId;

$eventId = EcrEventId::tryFrom($result['eventId']);
if ($eventId?->isSuccessful()) {
    echo 'Transaction reussie : ' . $result['transactionId'];
} else {
    echo 'Echec : ' . $eventId?->label();
}

// Annuler une session active
$isv->terminals->abort('session-uuid', 'PratiConnect-CR1');

// Consulter une session
$session = $isv->terminals->getSession('session-uuid');

// Lister les sessions d'une journee
$sessions = $isv->terminals->listSessions('2026-03-18');
```

**Notes** :
- La pre-autorisation n'est PAS supportee via Cloud Terminal ISV (utiliser Smart Checkout avec `preauth: true`)
- L'abort utilise GET (pas DELETE) — particularite de l'API Viva
- Le `sessionId` est auto-genere si non fourni
- Le SDK construit `isvDetails` automatiquement

---

### 6. Transfers — transferts marketplace

**Propriete** : `$isv->transfers`

Envoi et annulation de transferts de fonds vers les comptes connectes.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `send` | `send(string $targetAccountId, int $amount, ?string $sourceWalletId, ?string $transactionId, ?string $description)` | `array{transferId}` |
| `reverse` | `reverse(string $transferId, ?int $amount)` | `array{transferId}` |

```php
// Envoyer des fonds a un vendeur
$transfer = $isv->transfers->send(
    targetAccountId: 'seller-account-uuid',
    amount: 1000,                           // 10,00 EUR
    transactionId: 'txn-uuid',               // Lier a une transaction
    description: 'Commission mars 2026',
);

echo $transfer['transferId'];

// Annuler totalement un transfert
$isv->transfers->reverse('transfer-uuid');

// Annuler partiellement (5,00 EUR)
$isv->transfers->reverse('transfer-uuid', amount: 500);
```

---

### 7. MarketplaceOrders — ordres marketplace

**Propriete** : `$isv->marketplace`

Ordres marketplace avec transfert automatique vers le vendeur et gestion des reversals.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `create` | `create(int $amount, string $sellerAccountId, int $sellerAmount, ?string $customerDescription, ?string $merchantReference, ?string $sourceCode, bool $preauth)` | `array{order_code, checkout_url, platform_fee}` |
| `cancel` | `cancel(string $transactionId, ?int $amount, bool $reverseTransfers, bool $refundPlatformFee)` | `array` |

```php
// Creer un ordre marketplace
$order = $isv->marketplace->create(
    amount: 1500,                    // 15,00 EUR total
    sellerAccountId: 'seller-uuid',
    sellerAmount: 1200,              // 12,00 EUR au vendeur
    customerDescription: 'Achat marketplace',
);

echo $order['order_code'];
echo $order['checkout_url'];
echo $order['platform_fee'];  // 300 (3,00 EUR de commission)

// Remboursement total avec reversal des transferts
$isv->marketplace->cancel('txn-uuid');

// Remboursement partiel sans rembourser la commission plateforme
$isv->marketplace->cancel(
    transactionId: 'txn-uuid',
    amount: 500,
    reverseTransfers: true,
    refundPlatformFee: false,
);
```

---

### 8. NativeCheckoutIsv — paiement natif ISV

**Propriete** : `$isv->nativeCheckout`

Paiement natif server-to-server pour les marchands connectes, sans redirection Smart Checkout.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `createChargeToken` | `createChargeToken(string $connectedMerchantId, int $amount, string $paymentData, int $paymentMethodId)` | `array{chargeToken}` |
| `createTransaction` | `createTransaction(string $connectedMerchantId, string $chargeToken, int $amount, int $isvAmount, int $currencyCode, ?string $merchantTrns, ?string $customerTrns, bool $preauth)` | `array{transactionId, statusId}` |

**Flux** :
1. Le client collecte les donnees carte via le JS SDK Viva et obtient `paymentData` (chiffre)
2. Votre serveur cree un charge token via `createChargeToken()`
3. Votre serveur execute la transaction via `createTransaction()`

```php
// Etape 1 : creer un charge token
$token = $isv->nativeCheckout->createChargeToken(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    paymentData: $encryptedCardData,  // Du JS SDK Viva
);

// Etape 2 : executer la transaction
$txn = $isv->nativeCheckout->createTransaction(
    connectedMerchantId: 'merchant-uuid',
    chargeToken: $token['chargeToken'],
    amount: 1500,
    isvAmount: 100,
    merchantTrns: 'INV-2026-001',
    customerTrns: 'Consultation bien-etre',
);

echo $txn['transactionId'];
echo $txn['statusId'];
```

---

### 9. IsvWebhooks — CRUD webhooks ISV

**Propriete** : `$isv->isvWebhooks`

Creation, listing, mise a jour et suppression d'abonnements webhook ISV.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `create` | `create(string $url, string $eventType, ?string $description)` | `array` (avec `webhookId`) |
| `list` | `list()` | `array` |
| `update` | `update(string $webhookId, string $url, ?string $eventType)` | `array` |
| `delete` | `delete(string $webhookId)` | `array` |

```php
// Creer un webhook
$webhook = $isv->isvWebhooks->create(
    url: 'https://myapp.com/webhooks/viva',
    eventType: 'transaction.payment.created',
    description: 'Notifications de paiement',
);

// Lister
$webhooks = $isv->isvWebhooks->list();

// Modifier
$isv->isvWebhooks->update(
    webhookId: $webhook['webhookId'],
    url: 'https://myapp.com/webhooks/viva-v2',
    eventType: 'transaction.refund.created',
);

// Supprimer
$isv->isvWebhooks->delete($webhook['webhookId']);
```

---

### 10. Webhooks — verification et parsing

**Propriete** : `$isv->webhooks`

Verification de la requete GET initiale de Viva Wallet et parsing des payloads POST (21 types d'evenements).

| Methode | Signature | Retour |
|---------|-----------|--------|
| `verificationResponse` | `verificationResponse(string $verificationKey)` | `array{StatusCode, Key}` |
| `parse` | `parse(string $rawBody)` | `array{event_type, event_type_id, event_data}` |
| `isKnownEvent` | `Webhooks::isKnownEvent(int $eventTypeId)` (statique) | `bool` |

```php
// GET — verification initiale
$verificationKey = config('services.viva.verification_key');
return response()->json(
    $isv->webhooks->verificationResponse($verificationKey)
);
// => {"StatusCode": 0, "Key": "votre-cle"}

// POST — parsing des evenements
$event = $isv->webhooks->parse(file_get_contents('php://input'));

echo $event['event_type'];     // 'transaction.payment.created'
echo $event['event_type_id'];  // 1796
$data = $event['event_data'];

switch ($event['event_type']) {
    case 'transaction.payment.created':
        // Traiter le paiement
        break;
    case 'transaction.refund.created':
        // Traiter le remboursement
        break;
    case 'account.connected':
        // Nouveau marchand connecte
        break;
}

// Verifier si un EventTypeId est connu (methode statique)
use QrCommunication\VivaIsv\Resources\Webhooks;

if (Webhooks::isKnownEvent(1796)) {
    echo 'Evenement reconnu';
}
```

---

### 11. IsvMessages — webhooks merchant-level

**Propriete** : `$isv->messages`

Gestion des abonnements webhook au niveau marchand connecte via `/api/messages/config`.
Utilise le Composite Basic Auth automatiquement.

Contrairement aux webhooks ISV-level (`$isv->isvWebhooks`), ces abonnements sont
specifiques a chaque marchand connecte et couvrent des evenements bancaires (virements emis,
settlements) que Viva n'envoie pas automatiquement a l'ISV.

| Methode | Signature | Retour |
|---------|-----------|--------|
| `register` | `register(string $connectedMerchantId, int $eventTypeId, string $callbackUrl)` | `array{MessageId, EventTypeId, Url, IsActive}` |
| `list` | `list(string $connectedMerchantId)` | `array[]` |
| `delete` | `delete(string $connectedMerchantId, string $messageId)` | `array` |

**Events recommandes** :

| EventTypeId | Description |
|-------------|-------------|
| 768 | Command Bank Transfer Created (virement emis cree) |
| 769 | Command Bank Transfer Executed (virement emis execute) |
| 2054 | Account Transaction Created (settlements / fonds recus) |

```php
// Enregistrement bas-niveau (une seule subscription)
$isv->messages->register('merchant-uuid', 768, 'https://app.example.com/api/webhooks/viva');

// Lister toutes les subscriptions d'un marchand
$subscriptions = $isv->messages->list('merchant-uuid');

// Supprimer une subscription par son MessageId
$isv->messages->delete('merchant-uuid', $subscriptions[0]['MessageId']);
```

**Important** : si un webhook avec le meme URL + EventTypeId existe deja, Viva
retourne HTTP 400 "duplicate". Pour une registration idempotente, utiliser
`$isv->merchantWebhookRegistrar()` (cf. section suivante).

---

## Merchant-Level Webhook Registration

Le helper `MerchantWebhookRegistrar` encapsule `IsvMessages::register()` pour rendre la
registration **idempotente** : un doublon HTTP 400 est traite comme un succes silencieux.
Cela permet d'appeler cette methode a chaque provisioning de marchand sans risque d'erreur.

```php
// Enregistrer les 3 events bancaires en une fois (768, 769, 2054)
$results = $isv->merchantWebhookRegistrar()->registerAll(
    connectedMerchantId: $merchantId,
    callbackUrl: 'https://app.example.com/api/webhooks/viva',
);

foreach ($results as $r) {
    // $r['event_id']  : int (768, 769, 2054)
    // $r['status']    : 'created' | 'already_exists' | 'failed'
    // $r['message']   : string (seulement si 'failed')
    echo "Event {$r['event_id']} : {$r['status']}\n";
}
```

```php
// Enregistrer des events personnalises
$results = $isv->merchantWebhookRegistrar()->registerAll(
    connectedMerchantId: $merchantId,
    callbackUrl: 'https://app.example.com/api/webhooks/viva',
    events: [768 => 'Bank Transfer Created'],
);
```

### Difference entre webhooks ISV-level et merchant-level

| Niveau | Resource | Endpoint | Auth | Quand utiliser |
|--------|----------|----------|------|----------------|
| **ISV-level** | `$isv->isvWebhooks` | `/isv/v1/webhooks` | Bearer | Events auto-broadcast : transactions (1796-1799), comptes (8193/8194), etc. |
| **Merchant-level** | `$isv->messages` | `/api/messages/config` | Composite | Events bancaires per-merchant : virements (768/769), settlements (2054) |

---

## IsvConfig — helpers d'environnement

```php
// Verifier l'environnement sans inspecter les URLs
$isv->getConfig()->isProduction(); // true en production
$isv->getConfig()->isSandbox();    // true en demo
```

---

## Architecture

```
VivaIsvClient
├── $accounts               → ConnectedAccounts   (7 methodes)
├── $isvAccounts            → IsvAccounts         (3 methodes)
├── $orders                 → IsvOrders           (2 methodes)
├── $transactions           → IsvTransactions     (5 methodes)
├── $terminals              → EcrTerminals        (6 methodes)
├── $transfers              → Transfers           (2 methodes)
├── $marketplace            → MarketplaceOrders   (2 methodes)
├── $nativeCheckout         → NativeCheckoutIsv   (2 methodes)
├── $isvWebhooks            → IsvWebhooks         (4 methodes)
├── $webhooks               → Webhooks            (3 methodes)
└── $messages               → IsvMessages         (3 methodes)
    merchantWebhookRegistrar() → MerchantWebhookRegistrar (1 methode, lazy)

39 methodes au total — 11 ressources
```

```
src/
├── VivaIsvClient.php              # Point d'entree, instancie les 11 ressources
├── IsvConfig.php                  # Configuration (6 credentials + environnement + isProduction/isSandbox)
├── HttpClient.php                 # Client HTTP (Bearer, Basic, Composite Auth)
├── Enums/
│   ├── Environment.php            # demo | production
│   ├── EcrEventId.php             # 8 codes resultat POS
│   └── TransactionEventId.php     # 24 codes de declin
├── Exceptions/
│   ├── VivaException.php          # Exception de base
│   ├── ApiException.php           # Erreurs API (4xx, 5xx)
│   └── AuthenticationException.php # Erreurs OAuth2 (401)
├── Helpers/
│   └── MerchantWebhookRegistrar.php # Helper idempotent merchant webhooks
└── Resources/
    ├── ConnectedAccounts.php      # 7 methodes
    ├── IsvAccounts.php            # 3 methodes
    ├── IsvOrders.php              # 2 methodes
    ├── IsvTransactions.php        # 5 methodes
    ├── EcrTerminals.php           # 6 methodes
    ├── Transfers.php              # 2 methodes
    ├── MarketplaceOrders.php      # 2 methodes
    ├── NativeCheckoutIsv.php      # 2 methodes
    ├── IsvWebhooks.php            # 4 methodes
    ├── IsvMessages.php            # 3 methodes
    └── Webhooks.php               # 3 methodes
```

---

## Authentification

Le SDK gere **3 modes d'authentification** automatiquement. L'utilisateur fournit les 6 credentials au constructeur et le SDK choisit le bon mode pour chaque appel.

| Mode | Username | Password | Utilise pour |
|------|----------|----------|-------------|
| **ISV OAuth2 (Bearer)** | `clientId` | `clientSecret` | Comptes, ordres ISV, terminaux, transferts, marketplace, native checkout, webhooks ISV |
| **ISV Basic Auth** | `merchantId` | `apiKey` | Operations Legacy sur le propre compte ISV |
| **Composite Basic Auth** | `{resellerId}:{connectedMerchantId}` | `resellerApiKey` | Transactions des marchands connectes (capture, recurrent, annulation) |

### Composite Basic Auth (non documente par Viva Wallet)

Le format `{ResellerID}:{ConnectedMerchantID}` comme username avec `{ResellerAPIKey}` comme password a ete decouvert empiriquement lors de la certification ISV. Ce format n'est pas documente dans la documentation officielle Viva Wallet.

Le SDK construit automatiquement ce header — vous n'avez qu'a passer le `connectedMerchantId` aux methodes de `$isv->transactions`.

### Token OAuth2

Le token Bearer est obtenu automatiquement et rafraichi avant expiration (marge de 60 secondes). Pour forcer un refresh :

```php
$isv->invalidateToken();
```

---

## Enums

### EcrEventId — codes resultat Cloud Terminal

```php
use QrCommunication\VivaIsv\Enums\EcrEventId;

$event = EcrEventId::tryFrom($session['eventId']);
$event->isSuccessful();  // true si SUCCESS (0)
$event->isTerminal();    // true si etat final (pas IN_PROGRESS)
$event->shouldPoll();    // true si IN_PROGRESS (1100)
$event->label();         // 'Transaction successful', 'Declined', etc.
```

| Valeur | Constante | Description |
|--------|-----------|-------------|
| 0 | `SUCCESS` | Transaction reussie |
| 1003 | `TERMINAL_TIMEOUT` | Terminal hors delai |
| 1006 | `DECLINED` | Transaction refusee |
| 1016 | `ABORTED` | Transaction annulee |
| 1020 | `INSUFFICIENT_FUNDS` | Fonds insuffisants |
| 1099 | `GENERIC_ERROR` | Erreur generique |
| 1100 | `IN_PROGRESS` | En cours (continuer le polling) |
| 6000 | `BAD_PARAMS` | Parametres invalides |

### TransactionEventId — codes de declin detailles

```php
use QrCommunication\VivaIsv\Enums\TransactionEventId;

$decline = TransactionEventId::tryFrom($session['transactionEventId']);
echo $decline->label();       // 'Insufficient funds'
echo $decline->testAmount();  // 9951 (montant pour declencher ce declin en demo)
```

| Valeur | Constante | Montant test |
|--------|-----------|--------------|
| 10001 | `REFER_TO_ISSUER` | — |
| 10003 | `INVALID_MERCHANT` | — |
| 10004 | `PICKUP_CARD` | — |
| 10005 | `DO_NOT_HONOR` | — |
| 10006 | `GENERAL_ERROR` | 9906 |
| 10012 | `INVALID_TRANSACTION` | — |
| 10013 | `INVALID_AMOUNT` | — |
| 10014 | `INVALID_CARD` | 9914 |
| 10030 | `FORMAT_ERROR` | — |
| 10041 | `LOST_CARD` | — |
| 10043 | `STOLEN_CARD` | 9920 |
| 10051 | `INSUFFICIENT_FUNDS` | 9951 |
| 10054 | `EXPIRED_CARD` | 9954 |
| 10055 | `INCORRECT_PIN` | — |
| 10057 | `NOT_PERMITTED_CARDHOLDER` | 9957 |
| 10058 | `NOT_PERMITTED_TERMINAL` | — |
| 10061 | `WITHDRAWAL_LIMIT` | 9961 |
| 10062 | `RESTRICTED_CARD` | — |
| 10063 | `SECURITY_VIOLATION` | — |
| 10065 | `ACTIVITY_LIMIT` | — |
| 10068 | `LATE_RESPONSE` | — |
| 10070 | `CALL_ISSUER` | — |
| 10075 | `PIN_TRIES_EXCEEDED` | — |
| 10200 | `UNMAPPED` | — |

### Environment

```php
use QrCommunication\VivaIsv\Enums\Environment;

// Passer en string ou en enum
$isv = new VivaIsvClient(..., environment: 'demo');
$isv = new VivaIsvClient(..., environment: Environment::PRODUCTION);
```

---

## Gestion des erreurs

Le SDK definit 3 exceptions dans le namespace `QrCommunication\VivaIsv\Exceptions` :

```
RuntimeException
└── VivaException         (base — httpStatus, responseBody, getErrorCode(), getErrorText())
    ├── ApiException      (erreurs API 4xx/5xx)
    └── AuthenticationException  (erreurs OAuth2 — httpStatus = 401)
```

```php
use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\Exceptions\AuthenticationException;

try {
    $order = $isv->orders->create('merchant-uuid', 1500, isvAmount: 100);
} catch (AuthenticationException $e) {
    // Credentials ISV invalides
    echo $e->getMessage(); // 'ISV OAuth2 authentication failed: ...'
} catch (ApiException $e) {
    // Erreur API (400, 404, 500, etc.)
    echo $e->getMessage();       // Message d'erreur
    echo $e->httpStatus;         // Code HTTP
    echo $e->getErrorCode();     // Code Viva (ErrorCode)
    echo $e->getErrorText();     // Texte Viva (ErrorText)
    print_r($e->responseBody);   // Body JSON complet
}
```

Les methodes `capture()` et `recurring()` lancent `ApiException` si `ErrorCode !== 0` dans la reponse Viva.

---

## Webhooks (21 evenements)

Le SDK reconnait 21 types d'evenements webhook :

| ID | Type |
|----|------|
| 1796 | `transaction.payment.created` |
| 1797 | `transaction.refund.created` |
| 1798 | `transaction.payment.cancelled` |
| 1799 | `transaction.reversal.created` |
| 1800 | `transaction.preauth.created` |
| 1801 | `transaction.preauth.completed` |
| 1802 | `transaction.preauth.cancelled` |
| 1810 | `pos.session.created` |
| 1811 | `pos.session.failed` |
| 1812 | `transaction.price.calculated` |
| 1813 | `transaction.failed` |
| 1819 | `account.connected` |
| 1820 | `account.verification.status.changed` |
| 1821 | `account.transaction.created` |
| 1822 | `command.bank.transfer.created` |
| 1823 | `command.bank.transfer.executed` |
| 1824 | `transfer.created` |
| 1825 | `obligation.created` |
| 1826 | `obligation.captured` |
| 1827 | `order.updated` |
| 1828 | `sale.transactions.file` |

### Gestion des webhooks ISV (CRUD)

```php
// Creer
$wh = $isv->isvWebhooks->create(
    url: 'https://myapp.com/webhooks/viva',
    eventType: 'transaction.payment.created',
);

// Lister
$all = $isv->isvWebhooks->list();

// Modifier
$isv->isvWebhooks->update($wh['webhookId'], url: 'https://myapp.com/v2/webhooks');

// Supprimer
$isv->isvWebhooks->delete($wh['webhookId']);
```

### Reception et parsing

```php
// Endpoint GET — verification initiale
public function verify(Request $request)
{
    return response()->json(
        $this->isv->webhooks->verificationResponse(
            config('services.viva.verification_key')
        )
    );
}

// Endpoint POST — reception des evenements
public function handle(Request $request)
{
    $event = $this->isv->webhooks->parse($request->getContent());

    match ($event['event_type']) {
        'transaction.payment.created' => $this->handlePayment($event['event_data']),
        'transaction.refund.created'  => $this->handleRefund($event['event_data']),
        'account.connected'           => $this->handleNewAccount($event['event_data']),
        default => null,
    };

    return response()->json(['status' => 'ok']);
}
```

---

## Pieges evites par le SDK

Ces 9 pieges ont ete decouverts lors de la certification ISV. Le SDK les gere automatiquement — vous n'avez pas a vous en soucier.

| # | Piege | Ce que le SDK fait pour vous |
|---|-------|------------------------------|
| 1 | **Composite Basic Auth** non documente (`ResellerID:MerchantID / ResellerAPIKey`) | Le `HttpClient` construit automatiquement le header composite pour chaque appel sur un marchand connecte |
| 2 | **camelCase vs PascalCase** — New API en camelCase, Legacy API en PascalCase | Chaque ressource envoie les parametres dans la bonne casse |
| 3 | **Pas de `sourceCode`** dans les ordres ISV (le marchand connecte utilise sa source par defaut) | `IsvOrders::create()` n'envoie jamais de `sourceCode` |
| 4 | **`isvDetails`** obligatoire pour les ventes POS ISV | `EcrTerminals::sale()` construit `isvDetails` automatiquement |
| 5 | **Abort POS** utilise GET (pas DELETE) avec `cashRegisterId` en query param | `EcrTerminals::abort()` utilise GET |
| 6 | **Endpoints ISV** differents des endpoints marchands (`/ecr/isv/v1/` vs `/ecr/v1/`) | Chaque ressource utilise le bon prefixe |
| 7 | **Token OAuth2** expire silencieusement | Le `HttpClient` rafraichit le token automatiquement avec une marge de 60s |
| 8 | **Reponses vides** (200 sans body) sur certains endpoints POS | Le `HttpClient` retourne `[]` au lieu de crash JSON |
| 9 | **`MerchantId`** en query string pour les ordres et native checkout ISV | Les methodes `create()` ajoutent automatiquement `?MerchantId=` |

---

## Test en sandbox

Utiliser `environment: 'demo'` pour tester sans transactions reelles.

### Carte de test

| Champ | Valeur |
|-------|--------|
| Numero | `4111 1111 1111 1111` |
| Expiration | Toute date future |
| CVV | `111` |

### Montants de test (declins)

Utiliser `TransactionEventId::testAmount()` pour obtenir les montants qui declenchent chaque type de declin en demo :

```php
use QrCommunication\VivaIsv\Enums\TransactionEventId;

// Montant qui declenche "Insufficient funds" en demo
$amount = TransactionEventId::INSUFFICIENT_FUNDS->testAmount(); // 9951

$order = $isv->orders->create('merchant-uuid', $amount);
```

| Montant (centimes) | Declin |
|--------------------|--------|
| 9951 | Fonds insuffisants |
| 9954 | Carte expiree |
| 9920 | Carte volee |
| 9957 | Carte non autorisee |
| 9961 | Limite de retrait |
| 9906 | Erreur generale |
| 9914 | Carte invalide |

---

## Documentation API interactive

Documentation interactive ReDoc disponible :

- **En ligne** : [qrcommunication.github.io/sdk-php-viva-isv](https://qrcommunication.github.io/sdk-php-viva-isv/)
- **Locale** : ouvrir `docs/index.html` dans un navigateur
- **OpenAPI** : `docs/openapi.yaml` ou `openapi.yaml` a la racine

---

## Intégration IA

Ce SDK inclut un **skill détaillé** (`skill/SKILL.md`) automatiquement détecté par les assistants IA. Il fournit la référence complète des 10 resources, 36+ méthodes, 3 modes d'auth, enums, exceptions, 9 pièges de certification et patterns d'implémentation.

| Outil | Fichier | Détection |
|-------|---------|-----------|
| **Claude Code** | `CLAUDE.md` + `skill/SKILL.md` | Automatique |
| **Cursor** | `.cursorrules` | Automatique |
| **GitHub Copilot** | `.github/copilot-instructions.md` | Automatique |
| **OpenAI Codex** | `AGENTS.md` | Automatique |

```php
// Un agent IA peut construire cet appel à partir de :
// "Crée un paiement ISV de 50€ avec 5€ de commission"
$order = $isv->orders->create(
    connectedMerchantId: $merchantId,
    amount: 5000,
    isvAmount: 500,
);
```

---

## Licence

[MIT](LICENSE) — QrCommunication
