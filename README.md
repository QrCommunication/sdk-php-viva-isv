# Viva Wallet ISV SDK for PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-8892BF.svg)](https://php.net)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--isv--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-isv-sdk)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1-6BA539.svg)](docs/openapi.yaml)
[![ReDoc](https://img.shields.io/badge/Docs-ReDoc-0052FF.svg)](https://qrcommunication.github.io/sdk-php-viva-isv/)

SDK PHP pour l'API **Viva Wallet ISV Partner** — comptes connectes, ordres ISV avec commission, transactions via Composite Auth, Cloud Terminal POS, transferts marketplace et webhooks.

> **Ce SDK couvre les operations ISV** (marketplace, comptes connectes, split payments). Pour les operations marchands standard, voir `sdk-php-viva-merchant`.

---

## Table des matieres

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Les 3 authentifications](#les-3-authentifications)
- [Les 3 hosts API](#les-3-hosts-api)
- [API Reference](#api-reference)
  - [ConnectedAccounts](#1-connectedaccounts)
  - [IsvOrders](#2-isvorders)
  - [IsvTransactions](#3-isvtransactions)
  - [EcrTerminals](#4-ecrterminals)
  - [Transfers](#5-transfers)
  - [MarketplaceOrders](#6-marketplaceorders)
  - [Webhooks](#7-webhooks)
- [Enums](#enums)
  - [EcrEventId](#ecreventid)
  - [TransactionEventId](#transactioneventid)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Architecture](#architecture)
- [Documentation API interactive](#documentation-api-interactive)
- [Integration AI](#integration-ai)
- [Pieges connus (certification ISV)](#pieges-connus-certification-isv)
- [Tests](#tests)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-isv-sdk
```

**Prerequis :** PHP 8.2+, extension `json`, extension `curl` (via Guzzle 7.8+).

---

## Quick Start

```php
use QrCommunication\VivaIsv\VivaIsvClient;

$isv = new VivaIsvClient(
    clientId:       'isv-client-id.apps.vivapayments.com',  // ISV OAuth
    clientSecret:   'isv-client-secret',
    merchantId:     'isv-merchant-uuid',                     // ISV Basic Auth
    apiKey:         'isv-api-key',
    resellerId:     'reseller-uuid',                         // Composite Auth
    resellerApiKey: 'reseller-api-key',
    environment:    'demo',                                  // 'demo' ou 'production'
);

// Verifier la connexion
if ($isv->testConnection()) {
    echo 'Connexion ISV OK';
}
```

Les 6 credentials sont **tous necessaires** pour couvrir les 3 mecanismes d'authentification ISV.

---

## Les 3 authentifications

Le SDK gere **3 mecanismes d'authentification** distincts, chacun cible un ensemble d'endpoints different :

| Type | Credentials | Format HTTP | Utilise pour |
|------|-------------|-------------|-------------|
| **ISV OAuth2** | `clientId` + `clientSecret` | `Authorization: Bearer {token}` | Comptes, ordres ISV, terminaux, transferts, marketplace |
| **ISV Basic Auth** | `merchantId` + `apiKey` | `Authorization: Basic {merchantId:apiKey}` | Propre compte ISV sur Legacy API |
| **Composite Basic Auth** | `resellerId` + `resellerApiKey` | `Authorization: Basic {compositeUsername:resellerApiKey}` | Transactions des marchands connectes |

### Composite Basic Auth (NON DOCUMENTE par Viva Wallet)

Ce mecanisme a ete **decouvert empiriquement** lors de la certification ISV. Viva Wallet ne le documente nulle part.

```
Username: {ResellerID}:{ConnectedMerchantID}   (deux UUIDs separes par :)
Password: {ResellerAPIKey}
```

Exemple concret :
```
ResellerID:          a1b2c3d4-0000-0000-0000-000000000000
ConnectedMerchantID: e5f6g7h8-0000-0000-0000-000000000000
ResellerAPIKey:      R3s3ll3rK3y...

=> Username: a1b2c3d4-0000-0000-0000-000000000000:e5f6g7h8-0000-0000-0000-000000000000
=> Password: R3s3ll3rK3y...
```

Le SDK gere automatiquement la construction du username via `IsvConfig::compositeUsername()`.

---

## Les 3 hosts API

Viva Wallet utilise **3 hosts differents** selon le type d'operation. Chaque host a sa propre convention de nommage des parametres :

| Host | Auth | Convention params | Endpoints |
|------|------|-------------------|-----------|
| `accounts.vivapayments.com` | Form POST (`clientId:clientSecret`) | -- | `/connect/token` uniquement |
| `api.vivapayments.com` | Bearer token | **camelCase** | `/checkout/v2/isv/`, `/checkout/v2/orders/`, `/ecr/isv/v1/`, `/platforms/v1/` |
| `www.vivapayments.com` | Basic Auth / Composite | **PascalCase** | `/api/orders`, `/api/transactions` |

> **CRITIQUE** : ne jamais melanger les casses. Legacy = PascalCase (`Amount`, `IsvAmount`), New API = camelCase (`amount`, `isvAmount`).

### Environnements

| Environnement | Accounts URL | API URL | Legacy URL |
|---------------|-------------|---------|------------|
| `demo` | `demo-accounts.vivapayments.com` | `demo-api.vivapayments.com` | `demo.vivapayments.com` |
| `production` | `accounts.vivapayments.com` | `api.vivapayments.com` | `www.vivapayments.com` |

---

## API Reference

### 1. ConnectedAccounts

Gestion des comptes marchands connectes sous la plateforme ISV.

**Auth :** Bearer ISV OAuth (New API) | **SDK :** `$isv->accounts`

#### Creer un compte connecte

```php
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://app.com/onboarding/complete',
    partnerName: 'Ma Plateforme',    // optionnel
    logoUrl: 'https://app.com/logo.png', // optionnel
);

echo $account['accountId'];                    // UUID du compte
echo $account['invitation']['redirectUrl'];    // URL KYB onboarding
```

`POST /platforms/v1/accounts`

#### Obtenir les details d'un compte

```php
$info = $isv->accounts->get($accountId);
echo $info['verificationStatus']; // Pending, Verified, Active, Rejected
echo $info['merchantId'];         // UUID marchand (apres verification)
```

`GET /platforms/v1/accounts/{accountId}`

#### Raccourcis utiles

```php
// Verification rapide
$isv->accounts->isVerified($accountId); // true si Verified ou Active

// URL d'onboarding (null si deja verifie)
$url = $isv->accounts->onboardingUrl($accountId);
```

#### Mettre a jour un compte

```php
$isv->accounts->update($accountId, [
    'email' => 'new-email@example.com',
    'branding' => ['partnerName' => 'Updated Name'],
]);
```

`POST /platforms/v1/accounts/{accountId}`

---

### 2. IsvOrders

Ordres Smart Checkout avec commission ISV pour les marchands connectes.

**Auth :** Bearer ISV OAuth (New API) | **SDK :** `$isv->orders`

#### Creer un ordre ISV

```php
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,                        // 15,00 EUR (centimes)
    isvAmount: 100,                      // 1,00 EUR commission ISV
    customerDescription: 'Consultation', // visible par le client
    merchantReference: 'session_123',    // reference interne
    allowRecurring: true,                // tokeniser la carte
    preauth: false,
);

echo $order['order_code'];   // Code de l'ordre
echo $order['checkout_url']; // URL Smart Checkout complete
// => https://demo.vivapayments.com/web/checkout?ref=1234567890
```

`POST /checkout/v2/isv/orders?MerchantId={uuid}`

#### Obtenir l'URL de checkout

```php
$url = $isv->orders->checkoutUrl($orderCode);
```

> **Validation SDK :** `isvAmount` ne peut pas depasser `amount`. Le SDK lance `InvalidArgumentException` avant l'appel API.

> **Important :** NE PAS envoyer `sourceCode` — le connected merchant utilise sa source par defaut.

---

### 3. IsvTransactions

Operations sur les transactions des marchands connectes : capture, recurring, cancel.

**Auth :** Composite Basic Auth (Legacy API) | **SDK :** `$isv->transactions`

> **Prerequis :** "Allow recurring payments and pre-auth captures via API" doit etre active dans ISV account Settings > API Access.

#### Details d'une transaction

```php
$txn = $isv->transactions->get('txn-uuid', 'merchant-uuid');
```

`GET /api/transactions/{transactionId}` (Composite Auth)

#### Lister par date

```php
$txns = $isv->transactions->listByDate('merchant-uuid', '2026-03-18');
// Retourne directement le tableau de transactions
```

`GET /api/transactions?date={date}` (Composite Auth)

#### Capturer un preauth

```php
$isv->transactions->capture(
    transactionId: 'preauth-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,       // en centimes
    isvAmount: 100,      // commission ISV
);
```

`POST /api/transactions/{transactionId}` avec `Amount` + `IsvAmount` (PascalCase)

#### Paiement recurrent

```php
$isv->transactions->recurring(
    initialTransactionId: 'initial-txn-uuid',
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,
    isvAmount: 100,
    sourceCode: null,    // optionnel
);
```

`POST /api/transactions/{initialTransactionId}` (Composite Auth)

#### Annuler / rembourser

```php
// Remboursement total
$isv->transactions->cancel('txn-uuid', 'merchant-uuid');

// Remboursement partiel (500 centimes = 5,00 EUR)
$isv->transactions->cancel('txn-uuid', 'merchant-uuid', amount: 500);
```

`DELETE /api/transactions/{transactionId}` (Composite Auth)

---

### 4. EcrTerminals

Operations POS terminal via Cloud Terminal API ISV.

**Auth :** Bearer ISV OAuth (New API) | **SDK :** `$isv->terminals`

#### Rechercher les terminaux

```php
$devices = $isv->terminals->search(merchantId: 'merchant-uuid');
$devices = $isv->terminals->search(); // tous les terminaux de la plateforme
```

`POST /ecr/isv/v1/devices:search`

#### Vente POS terminal

```php
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 100,                           // 1,00 EUR
    isvAmount: 10,                         // 0,10 EUR commission
    terminalMerchantId: 'merchant-uuid',
    cashRegisterId: 'CR-01',
    merchantReference: 'sale_456',         // optionnel
    currencyCode: 978,                     // EUR (defaut)
    sessionId: null,                       // auto-genere si null
);

echo $session['session_id']; // UUID de la session
```

`POST /ecr/isv/v1/transactions:sale`

#### Polling du resultat

```php
// Polling manuel
$result = $isv->terminals->getSession($session['session_id']);

// Polling automatique (attend le resultat ou timeout)
$result = $isv->terminals->pollUntilComplete(
    sessionId: $session['session_id'],
    timeoutSeconds: 120,   // defaut 120s
    intervalMs: 3000,      // defaut 3000ms
);

if ($result['success']) {
    echo "Transaction: {$result['transactionId']}";
} else {
    $eventId = EcrEventId::from($result['eventId']);
    echo "Erreur: {$eventId->label()}";
}
```

`GET /ecr/isv/v1/sessions/{sessionId}`

#### Lister les sessions

```php
$sessions = $isv->terminals->listSessions('2026-03-18');
```

`GET /ecr/isv/v1/sessions?date={date}`

#### Annuler une session en cours

```php
$isv->terminals->abort($sessionId, cashRegisterId: 'CR-01');
```

`GET /ecr/isv/v1/sessions:abort/{sessionId}?cashRegisterId={id}`

> **Attention :** Abort utilise GET (pas DELETE) avec `cashRegisterId` en query param.

> **Preauth ISV via Cloud Terminal n'est PAS supporte** par Viva. Le terminal retourne `eventId: 6000`. Utiliser Smart Checkout a la place.

---

### 5. Transfers

Transferts de fonds vers les comptes connectes (marketplace).

**Auth :** Bearer ISV OAuth (New API) | **SDK :** `$isv->transfers`

#### Envoyer des fonds

```php
// Transfert standalone
$result = $isv->transfers->send(
    targetAccountId: 'seller-account-uuid',
    amount: 1000,                                  // 10,00 EUR
    description: 'Paiement vendeur',
);

echo $result['transferId']; // UUID du transfert

// Transfert lie a une transaction existante
$result = $isv->transfers->send(
    targetAccountId: 'seller-account-uuid',
    amount: 1000,
    transactionId: 'txn-uuid',
    description: 'Commission vendeur',
);

// Transfert depuis un wallet specifique
$result = $isv->transfers->send(
    targetAccountId: 'seller-account-uuid',
    amount: 1000,
    sourceWalletId: 'wallet-uuid',
);
```

`POST /platforms/v1/transfers`

#### Annuler un transfert

```php
// Annulation totale
$isv->transfers->reverse('transfer-uuid');

// Annulation partielle (500 centimes)
$isv->transfers->reverse('transfer-uuid', amount: 500);
```

`POST /platforms/v1/transfers/{transferId}:reverse`

---

### 6. MarketplaceOrders

Ordres de paiement marketplace avec transfert automatique vers le vendeur.

**Auth :** Bearer ISV OAuth (New API) | **SDK :** `$isv->marketplace`

#### Creer un ordre marketplace

```php
$order = $isv->marketplace->create(
    amount: 5000,                                  // 50,00 EUR total
    sellerAccountId: 'seller-account-uuid',
    sellerAmount: 4000,                            // 40,00 EUR au vendeur
    customerDescription: 'Achat marketplace',
    merchantReference: 'order_789',
    sourceCode: null,                              // optionnel
    preauth: false,
);

echo $order['order_code'];    // Code de l'ordre
echo $order['checkout_url'];  // URL Smart Checkout
echo $order['platform_fee'];  // 1000 (= 50,00 - 40,00 = 10,00 EUR commission)
```

`POST /checkout/v2/orders/`

Le `transfer` parameter dans le body declenche la distribution automatique :
- `sellerAmount` -> va au vendeur
- `amount - sellerAmount` -> reste sur le compte plateforme (commission)

#### Annuler une transaction marketplace

```php
// Remboursement total avec reversal des transferts vendeur
$isv->marketplace->cancel('txn-uuid');

// Remboursement partiel
$isv->marketplace->cancel('txn-uuid', amount: 2500);

// Options avancees
$isv->marketplace->cancel(
    transactionId: 'txn-uuid',
    amount: null,                  // remboursement total
    reverseTransfers: true,        // annuler les transferts vendeur (defaut: true)
    refundPlatformFee: false,      // rembourser la commission plateforme (defaut: false)
);
```

`DELETE /api/transactions/{transactionId}` (Bearer auth)

---

### 7. Webhooks

Verification et parsing des evenements webhook Viva Wallet.

**Auth :** Aucune | **SDK :** `$isv->webhooks`

#### Verification (GET)

Viva Wallet envoie une requete GET pour valider l'URL du webhook. Repondre avec le JSON fourni par le SDK :

```php
$response = $isv->webhooks->verificationResponse('your-verification-key');
// => ['StatusCode' => 0, 'Key' => 'your-verification-key']

// Dans Laravel :
return response()->json($response);
```

#### Parser un evenement (POST)

```php
$event = $isv->webhooks->parse($request->getContent());

echo $event['event_type']; // ex. 'transaction.payment.created'
$data = $event['event_data'];

// Traiter selon le type
match ($event['event_type']) {
    'transaction.payment.created' => handlePayment($data),
    'transaction.refund.created'  => handleRefund($data),
    'pos.session.created'         => handlePosSession($data),
    default                       => logger()->info("Unhandled: {$event['event_type']}"),
};
```

#### Evenements supportes

| EventTypeId | event_type | Description |
|-------------|-----------|-------------|
| 1796 | `transaction.payment.created` | Paiement effectue |
| 1797 | `transaction.refund.created` | Remboursement effectue |
| 1798 | `transaction.payment.cancelled` | Paiement annule |
| 1799 | `transaction.reversal.created` | Reversal cree |
| 1800 | `transaction.preauth.created` | Pre-autorisation creee |
| 1801 | `transaction.preauth.completed` | Pre-autorisation capturee |
| 1802 | `transaction.preauth.cancelled` | Pre-autorisation annulee |
| 1810 | `pos.session.created` | Session POS creee |
| 1811 | `pos.session.failed` | Session POS echouee |

---

## Enums

### EcrEventId

Codes evenements Cloud Terminal, retournes dans `eventId` lors du polling.

```php
use QrCommunication\VivaIsv\Enums\EcrEventId;

$event = EcrEventId::from(1100);
$event->shouldPoll();   // true — continuer a poller
$event->isTerminal();   // false — pas encore un etat final
$event->isSuccessful(); // false
$event->label();        // 'In progress'
```

| eventId | Enum | Signification | `shouldPoll()` | `isTerminal()` |
|---------|------|---------------|-----------------|-----------------|
| `0` | `SUCCESS` | Transaction reussie | `false` | `true` |
| `1003` | `TERMINAL_TIMEOUT` | Terminal timeout | `false` | `true` |
| `1006` | `DECLINED` | Refusee par le serveur | `false` | `true` |
| `1016` | `ABORTED` | Annulee (abort reussi) | `false` | `true` |
| `1020` | `INSUFFICIENT_FUNDS` | Fonds insuffisants | `false` | `true` |
| `1099` | `GENERIC_ERROR` | Erreur generique | `false` | `true` |
| `1100` | `IN_PROGRESS` | En cours | **`true`** | `false` |
| `6000` | `BAD_PARAMS` | Parametres incorrects | `false` | `true` |

### TransactionEventId

Codes raison de declin, retournes dans `transactionEventId` lors du polling.

```php
use QrCommunication\VivaIsv\Enums\TransactionEventId;

$decline = TransactionEventId::from(10051);
$decline->label();      // 'Insufficient funds'
$decline->testAmount(); // 9951 (montant en centimes qui declenche ce declin en demo)
```

| EventId | Enum | Description | `testAmount()` |
|---------|------|-------------|----------------|
| `10001` | `REFER_TO_ISSUER` | Refer to issuer | `0` |
| `10003` | `INVALID_MERCHANT` | Invalid merchant | `0` |
| `10004` | `PICKUP_CARD` | Pickup card | `0` |
| `10005` | `DO_NOT_HONOR` | Do not honor | `0` |
| `10006` | `GENERAL_ERROR` | General error | `9906` |
| `10012` | `INVALID_TRANSACTION` | Invalid transaction | `0` |
| `10013` | `INVALID_AMOUNT` | Invalid amount | `0` |
| `10014` | `INVALID_CARD` | Invalid card | `9914` |
| `10030` | `FORMAT_ERROR` | Format error | `0` |
| `10041` | `LOST_CARD` | Lost card | `0` |
| `10043` | `STOLEN_CARD` | Stolen card | `9920` |
| `10051` | `INSUFFICIENT_FUNDS` | Insufficient funds | `9951` |
| `10054` | `EXPIRED_CARD` | Expired card | `9954` |
| `10055` | `INCORRECT_PIN` | Incorrect PIN | `0` |
| `10057` | `NOT_PERMITTED_CARDHOLDER` | Card not permitted | `9957` |
| `10058` | `NOT_PERMITTED_TERMINAL` | Not permitted terminal | `0` |
| `10061` | `WITHDRAWAL_LIMIT` | Withdrawal limit exceeded | `9961` |
| `10062` | `RESTRICTED_CARD` | Restricted card | `0` |
| `10063` | `SECURITY_VIOLATION` | Security violation | `0` |
| `10065` | `ACTIVITY_LIMIT` | Activity limit | `0` |
| `10068` | `LATE_RESPONSE` | Late response | `0` |
| `10070` | `CALL_ISSUER` | Call issuer | `0` |
| `10075` | `PIN_TRIES_EXCEEDED` | PIN tries exceeded | `0` |
| `10200` | `UNMAPPED` | Unmapped decline | `0` |

---

## Gestion des erreurs

```php
use QrCommunication\VivaIsv\Exceptions\AuthenticationException;
use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\Exceptions\VivaException;

try {
    $order = $isv->orders->create(...);
} catch (AuthenticationException $e) {
    // Credentials ISV invalides (401)
    echo "Auth failed: {$e->getMessage()}";
} catch (ApiException $e) {
    // Erreur API Viva (4xx/5xx)
    echo "Error [{$e->httpStatus}]: {$e->getMessage()}";
    echo "Viva error code: {$e->getErrorCode()}";
    echo "Viva error text: {$e->getErrorText()}";
    echo "Response body: " . json_encode($e->responseBody);
} catch (VivaException $e) {
    // Exception de base (toutes heritent de celle-ci)
}
```

### Hierarchie des exceptions

```
VivaException (RuntimeException)
  |-- httpStatus: int
  |-- responseBody: ?array
  |-- getErrorCode(): ?int
  |-- getErrorText(): ?string
  |
  +-- AuthenticationException  — OAuth2 / credentials invalides (401)
  +-- ApiException             — Erreur API generale (4xx, 5xx)
```

### Validation cote client

Le SDK valide certaines regles avant l'appel API et lance `InvalidArgumentException` :

- `isvAmount > amount` dans `IsvOrders::create()`
- Payload webhook invalide dans `Webhooks::parse()`

---

## Architecture

```
VivaIsvClient (point d'entree)
+-- accounts     -> ConnectedAccounts  (New API, Bearer    — /platforms/v1/)
+-- orders       -> IsvOrders          (New API, Bearer    — /checkout/v2/isv/)
+-- transactions -> IsvTransactions    (Legacy API, Composite Basic Auth)
+-- terminals    -> EcrTerminals       (New API, Bearer    — /ecr/isv/v1/)
+-- transfers    -> Transfers          (New API, Bearer    — /platforms/v1/)
+-- marketplace  -> MarketplaceOrders  (New API, Bearer    — /checkout/v2/orders/)
+-- webhooks     -> Webhooks           (pas d'auth)
```

### Structure du code

```
src/
+-- VivaIsvClient.php          # Point d'entree, expose les 7 modules
+-- IsvConfig.php              # Configuration (3 jeux de credentials + URLs)
+-- HttpClient.php             # Client HTTP avec 3 modes d'auth + token cache
+-- Enums/
|   +-- Environment.php        # DEMO / PRODUCTION (URLs par env)
|   +-- EcrEventId.php         # Event IDs Cloud Terminal (8 cas)
|   +-- TransactionEventId.php # Codes de declin (24 cas + testAmount)
+-- Exceptions/
|   +-- VivaException.php      # Exception de base (httpStatus, responseBody)
|   +-- ApiException.php       # Erreur API
|   +-- AuthenticationException.php  # Echec OAuth2 (401)
+-- Resources/
    +-- ConnectedAccounts.php  # CRUD comptes marchands
    +-- IsvOrders.php          # Smart Checkout avec commission
    +-- IsvTransactions.php    # Capture, recurring, cancel (Composite Auth)
    +-- EcrTerminals.php       # POS terminal (sale, poll, abort)
    +-- Transfers.php          # Transferts vers comptes connectes
    +-- MarketplaceOrders.php  # Ordres marketplace avec split
    +-- Webhooks.php           # Verification + parsing evenements
```

### Diagramme d'architecture

```
                    +-------------------+
                    |  VivaIsvClient    |
                    |  (point d'entree) |
                    +--------+----------+
                             |
              +--------------+--------------+
              |                             |
    +---------v---------+       +-----------v-----------+
    |    IsvConfig       |       |      HttpClient       |
    |  3 credential sets |       |  3 auth modes + cache |
    +--------------------+       +-----+-----+-----+----+
                                       |     |     |
                          +------------+     |     +------------+
                          |                  |                  |
               +----------v----+   +---------v------+  +-------v----------+
               | Bearer (New)  |   | Basic (Legacy) |  | Composite (ISV)  |
               | api.viva...   |   | www.viva...    |  | www.viva...      |
               +-------+-------+   +-------+--------+  +-------+----------+
                       |                   |                    |
          +------------+-----+             |                    |
          |      |     |     |             |                    |
       Accounts Orders ECR Transfers  Transactions         Transactions
       Marketplace                    (own account)       (connected merchants)
```

---

## Documentation API interactive

La specification OpenAPI 3.1 complete est disponible :

- **Fichier :** [`docs/openapi.yaml`](docs/openapi.yaml) (egalement a la racine : [`openapi.yaml`](openapi.yaml))
- **Documentation interactive :** **[ReDoc sur GitHub Pages](https://qrcommunication.github.io/sdk-php-viva-isv/)**

La documentation ReDoc inclut :
- Tous les endpoints documentes avec exemples de requetes/reponses
- Schemas de donnees detailles
- Les 3 mecanismes d'authentification expliques (incluant le Composite Basic Auth non documente)
- Codes d'erreur, evenements webhook et enums
- Pieges decouverts lors de la certification ISV

---

## Integration AI

Ce SDK inclut des fichiers d'instructions automatiquement detectes par les assistants AI :

| Outil | Fichier | Detection |
|-------|---------|-----------|
| **Claude Code** | [`CLAUDE.md`](CLAUDE.md) | Automatique |
| **Cursor** | [`.cursorrules`](.cursorrules) | Automatique |
| **GitHub Copilot** | [`.github/copilot-instructions.md`](.github/copilot-instructions.md) | Automatique |
| **OpenAI Codex** | [`AGENTS.md`](AGENTS.md) | Automatique |
| **Gemini** | [`CLAUDE.md`](CLAUDE.md) | Manuel (copier dans le contexte) |

Ces fichiers fournissent a l'assistant AI :
- L'architecture ISV et les 3 modes d'authentification
- Le Composite Basic Auth (non documente par Viva)
- Le routing entre les 3 hosts API et les conventions de casse
- Les 9 pieges ISV decouverts lors de la certification
- Des exemples de code complets pour chaque resource

---

## Pieges connus (certification ISV)

Les 9 pieges suivants ont ete decouverts lors de la certification ISV. Ils sont geres automatiquement par le SDK, mais il est important de les connaitre :

| # | Piege | Consequence | Solution SDK |
|---|-------|-------------|-------------|
| 1 | **Bearer token sur Legacy API** | `401 Unauthorized` | `HttpClient` route automatiquement via `compositePost/Get/Delete` |
| 2 | **ISV Basic Auth sur transaction marchand connecte** | `"api action disabled"` | Composite Auth utilise automatiquement |
| 3 | **Connected merchant Basic Auth + IsvAmount** | `PaymentsRecurringIsvMissingReseller` | Le SDK injecte le contexte reseller |
| 4 | **`isvAmount > amount`** | Rejete par l'API | `InvalidArgumentException` cote client |
| 5 | **`scope=isv` dans le token** | `invalid_scope` | Aucun scope envoye |
| 6 | **Preauth ISV via Cloud Terminal** | `eventId: 6000` | Utiliser Smart Checkout (`$isv->orders->create(preauth: true)`) |
| 7 | **Capture preauth sans activation** | Echec silencieux | Documentation + verification |
| 8 | **Abort ECR utilise GET pas DELETE** | `405 Method Not Allowed` | `$isv->terminals->abort()` utilise GET |
| 9 | **`sourceCode` dans les ordres ISV** | Rejet ou mauvais routage | NE PAS envoyer — le SDK ne l'inclut pas |

---

## Tests

### Carte de test (demo)

| Champ | Valeur |
|-------|--------|
| Numero | `4111111111111111` |
| CVV | `111` |
| Expiration | N'importe quelle date future |
| 3DS password | `Secret!33` |

### Montants de declin (centimes)

Ces montants declenchent un declin specifique en environnement demo :

| Centimes | TransactionEventId | Enum | Description |
|----------|-------------------|------|-------------|
| `9951` | 10051 | `INSUFFICIENT_FUNDS` | Fonds insuffisants |
| `9954` | 10054 | `EXPIRED_CARD` | Carte expiree |
| `9920` | 10043 | `STOLEN_CARD` | Carte volee |
| `9957` | 10057 | `NOT_PERMITTED_CARDHOLDER` | Carte non autorisee |
| `9961` | 10061 | `WITHDRAWAL_LIMIT` | Limite de retrait |
| `9906` | 10006 | `GENERAL_ERROR` | Erreur generale |
| `9914` | 10014 | `INVALID_CARD` | Carte invalide |

### Lancer les tests

```bash
composer test
```

---

## Licence

[MIT](LICENSE)

---

<p align="center">
  Developpe par <a href="https://qrcommunication.com"><strong>QrCommunication</strong></a>
</p>

<p align="center">
  <a href="https://qrcommunication.com">https://qrcommunication.com</a>
</p>
