# Viva Wallet ISV SDK for PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-8892BF.svg)](https://php.net)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--isv--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-isv-sdk)

SDK PHP pour l'API **Viva Wallet ISV Partner** — comptes connectés, ordres ISV avec commission, composite auth, Cloud Terminal POS.

> **Ce SDK couvre les opérations ISV** (marketplace, comptes connectés, split payments). Pour les opérations marchands standard, voir `sdk-php-viva-merchant`.

---

## Table des matières

- [Installation](#installation)
- [Configuration](#configuration)
- [Comptes connectés](#comptes-connectés)
- [Ordres ISV (Smart Checkout)](#ordres-isv-smart-checkout)
- [Transactions ISV (Composite Auth)](#transactions-isv-composite-auth)
- [POS Terminal (Cloud Terminal)](#pos-terminal-cloud-terminal)
- [Webhooks](#webhooks)
- [Enums utiles](#enums-utiles)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Architecture](#architecture)
- [Documentation API](#documentation-api)
- [Pièges connus](#pièges-connus)
- [Tests](#tests)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-isv-sdk
```

**Prérequis :** PHP 8.2+, extension `json`, extension `curl` (via Guzzle).

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
    environment: 'demo', // ou 'production'
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

### Environnements

| Environnement | Accounts URL | API URL | Legacy URL |
|---------------|-------------|---------|------------|
| `demo` | `demo-accounts.vivapayments.com` | `demo-api.vivapayments.com` | `demo.vivapayments.com` |
| `production` | `accounts.vivapayments.com` | `api.vivapayments.com` | `www.vivapayments.com` |

### Vérifier la connexion

```php
if ($isv->testConnection()) {
    echo 'Connexion ISV OK';
}
```

---

## Comptes connectés

```php
// Créer un compte marchand connecté
$account = $isv->accounts->create(
    email: 'merchant@example.com',
    returnUrl: 'https://app.com/onboarding/complete',
    partnerName: 'Ma Plateforme',
);
echo $account['accountId'];
echo $account['invitation']['redirectUrl']; // URL KYB onboarding

// Obtenir les détails
$info = $isv->accounts->get($accountId);
echo $info['verificationStatus']; // Pending, Verified, Rejected

// Raccourci vérification
$isv->accounts->isVerified($accountId); // true/false

// URL d'onboarding (si pas encore vérifié)
$url = $isv->accounts->onboardingUrl($accountId);
```

---

## Ordres ISV (Smart Checkout)

```php
// Créer un ordre avec commission ISV
$order = $isv->orders->create(
    connectedMerchantId: 'merchant-uuid',
    amount: 1500,                        // 15,00 €
    isvAmount: 100,                      // 1,00 € commission ISV
    customerDescription: 'Consultation',
    merchantReference: 'session_123',
    allowRecurring: true,
    preauth: false,
);

echo $order['checkout_url'];
// => https://demo.vivapayments.com/web/checkout?ref=1234567890
```

> **Validation SDK :** `isvAmount` ne peut pas dépasser `amount`. Le SDK lance une `InvalidArgumentException` avant l'appel API.

---

## Transactions ISV (Composite Auth)

Toutes les opérations sur les transactions des marchands connectés utilisent le **Composite Basic Auth** automatiquement.

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

> **Prérequis :** "Allow recurring payments and pre-auth captures via API" doit être activé dans Settings > API Access.

---

## POS Terminal (Cloud Terminal)

```php
// Rechercher les terminaux
$devices = $isv->terminals->search(merchantId: 'merchant-uuid');

// Envoyer une vente au terminal
$session = $isv->terminals->sale(
    terminalId: 16014231,
    amount: 100,                         // 1,00 €
    isvAmount: 10,                       // 0,10 € commission
    terminalMerchantId: 'merchant-uuid',
    cashRegisterId: 'CR-01',
    merchantReference: 'sale_456',
);

echo $session['session_id']; // UUID de la session

// Polling manuel
$result = $isv->terminals->getSession($session['session_id']);

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
| `0` | Succès | Transaction complétée |
| `1003` | Terminal timeout | Réessayer |
| `1006` | Refusée | Afficher erreur |
| `1016` | Annulée (abort) | Confirmer annulation |
| `1020` | Fonds insuffisants | Afficher message |
| `1099` | Erreur générique | Réessayer ou escalader |
| `1100` | En cours | Continuer à poller |
| `6000` | Paramètres incorrects | Corriger la requête |

> **Preauth ISV via Cloud Terminal n'est PAS supporté** par Viva. Le terminal retourne `eventId: 6000`. Utiliser Smart Checkout à la place.

---

## Webhooks

```php
// Vérification (GET) — répondre à la requête de validation Viva
$response = $isv->webhooks->verificationResponse('your-verification-key');
// => ['StatusCode' => 0, 'Key' => 'your-verification-key']

// Parser un événement (POST)
$event = $isv->webhooks->parse($request->getContent());
// => ['event_type' => 'transaction.payment.created', 'event_data' => [...]]
```

### Événements supportés

| EventTypeId | event_type |
|-------------|-----------|
| 1796 | `transaction.payment.created` |
| 1797 | `transaction.refund.created` |
| 1798 | `transaction.payment.cancelled` |
| 1799 | `transaction.reversal.created` |
| 1800 | `transaction.preauth.created` |
| 1801 | `transaction.preauth.completed` |
| 1802 | `transaction.preauth.cancelled` |
| 1810 | `pos.session.created` |
| 1811 | `pos.session.failed` |

---

## Enums utiles

```php
use QrCommunication\VivaIsv\Enums\EcrEventId;
use QrCommunication\VivaIsv\Enums\TransactionEventId;

// Cloud Terminal events
$event = EcrEventId::from(1100);
$event->shouldPoll();  // true
$event->isTerminal();  // false
$event->label();       // 'In progress'

// Transaction decline codes
$decline = TransactionEventId::from(10051);
$decline->label();      // 'Insufficient funds'
$decline->testAmount(); // 9951 (montant en centimes qui déclenche ce déclin en demo)
```

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
    // Erreur API Viva
    echo "Error [{$e->httpStatus}]: {$e->getMessage()}";
    echo "Viva error code: {$e->getErrorCode()}";
    echo "Viva error text: {$e->getErrorText()}";
} catch (VivaException $e) {
    // Exception de base (toutes héritent de celle-ci)
}
```

### Hiérarchie des exceptions

```
VivaException (RuntimeException)
├── AuthenticationException  — OAuth2 / credentials invalides (401)
└── ApiException             — Erreur API générale (4xx, 5xx)
```

---

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

| Auth | Méthode HTTP | Quand |
|------|-------------|-------|
| **Bearer ISV OAuth** | `Authorization: Bearer {token}` | Comptes, ordres, terminaux |
| **Basic Auth ISV** | `Authorization: Basic {merchantId:apiKey}` | Propre compte Legacy API |
| **Composite Basic Auth** | `Authorization: Basic {resellerId:merchantId / resellerApiKey}` | Transactions marchands connectés |

### Structure du code

```
src/
├── VivaIsvClient.php          # Point d'entrée, expose les 5 modules
├── IsvConfig.php              # Configuration (3 jeux de credentials + URLs)
├── HttpClient.php             # Client HTTP avec 3 modes d'auth + token cache
├── Enums/
│   ├── Environment.php        # DEMO / PRODUCTION (URLs par env)
│   ├── EcrEventId.php         # Event IDs Cloud Terminal (8 cas)
│   └── TransactionEventId.php # Codes de déclin (20 cas + testAmount)
├── Exceptions/
│   ├── VivaException.php      # Exception de base (httpStatus, responseBody)
│   ├── ApiException.php       # Erreur API
│   └── AuthenticationException.php  # Échec OAuth2 (401)
└── Resources/
    ├── ConnectedAccounts.php  # CRUD comptes marchands
    ├── IsvOrders.php          # Smart Checkout avec commission
    ├── IsvTransactions.php    # Capture, recurring, cancel (Composite Auth)
    ├── EcrTerminals.php       # POS terminal (sale, poll, abort)
    └── Webhooks.php           # Vérification + parsing événements
```

---

## Documentation API

La spécification OpenAPI complète est disponible dans [`openapi.yaml`](openapi.yaml).

### Documentation interactive ReDoc

**[Consulter la documentation interactive](https://qrcommunication.github.io/sdk-php-viva-isv/)**

La documentation ReDoc inclut :

- Tous les endpoints documentés avec exemples
- Schémas de requête/réponse détaillés
- Les 3 mécanismes d'authentification expliqués
- Codes d'erreur et événements webhook

---

## Intégration AI (Claude, Cursor, Copilot, Codex)

Ce SDK inclut des fichiers d'instructions automatiquement détectés par les assistants AI :

| Outil | Fichier | Détection |
|-------|---------|-----------|
| **Claude Code** | [`CLAUDE.md`](CLAUDE.md) | Automatique |
| **Cursor** | [`.cursorrules`](.cursorrules) | Automatique |
| **GitHub Copilot** | [`.github/copilot-instructions.md`](.github/copilot-instructions.md) | Automatique |
| **OpenAI Codex** | [`AGENTS.md`](AGENTS.md) | Automatique |
| **Gemini** | [`CLAUDE.md`](CLAUDE.md) | Manuel (copier dans le contexte) |

Ces fichiers fournissent à l'assistant AI :
- L'architecture ISV et les 3 modes d'authentification
- Le Composite Basic Auth (non documenté par Viva)
- Le routing entre les 3 hosts API
- Les pièges ISV découverts lors de la certification
- Des exemples de code complets pour chaque resource

---

## Pièges connus

1. **Bearer token sur Legacy API** → 401. L'API legacy n'accepte QUE Basic Auth.
2. **ISV Basic Auth + transaction marchand** → `"api action disabled"`. Il faut le Composite Auth.
3. **Connected merchant Basic Auth + IsvAmount** → `PaymentsRecurringIsvMissingReseller`. Le Composite Auth est obligatoire.
4. **`isvAmount > amount`** → Rejeté par l'API. Le SDK valide cela côté client.
5. **`scope=isv` dans le token** → `invalid_scope`. Ne pas envoyer de scope explicite.
6. **Preauth ISV Cloud Terminal** → `eventId: 6000`. Utiliser Smart Checkout à la place.
7. **Capture preauth** nécessite "Allow recurring payments and pre-auth captures via API" activé dans les Settings.

---

## Tests

### Carte de test

| Champ | Valeur |
|-------|--------|
| Numéro | `4111111111111111` |
| CVV | `111` |
| Expiration | N'importe quelle date future |
| 3DS password | `Secret!33` |

### Montants de test (déclenchent un déclin en démo)

| Centimes | EventId | Description |
|----------|---------|-------------|
| `9951` | 10051 | Insufficient funds |
| `9954` | 10054 | Expired card |
| `9920` | 10200 | Stolen card |
| `9957` | 10057 | Card not permitted |
| `9961` | 10061 | Withdrawal limit |
| `9906` | 10006 | General error |
| `9914` | 10014 | Invalid card |

### Lancer les tests

```bash
composer test
```

---

## Licence

[MIT](LICENSE)

---

<p align="center">
  Développé par <a href="https://qrcommunication.com"><strong>QrCommunication</strong></a>
</p>

<p align="center">
  <a href="https://qrcommunication.com">https://qrcommunication.com</a>
</p>
