# Viva Wallet ISV SDK — Agent Instructions

See [CLAUDE.md](CLAUDE.md) for complete SDK architecture, patterns, and implementation guidelines.

## Quick Reference

- **Package**: `qrcommunication/viva-isv-sdk`
- **Namespace**: `QrCommunication\VivaIsv\`
- **Entry point**: `VivaIsvClient`
- **Resources**: 11 (`accounts`, `isvAccounts`, `orders`, `transactions`, `terminals`, `transfers`, `marketplace`, `nativeCheckout`, `isvWebhooks`, `webhooks`, `messages`)
- **Helpers**: `merchantWebhookRegistrar()` (lazy, idempotent merchant webhook registration)
- **Pattern**: Resource pattern (`$isv->orders->create()`)
- **3 auth modes**: Bearer (ISV OAuth), Basic Auth (own merchant), Composite Basic Auth (connected merchants)
- **Composite Auth** (undocumented): `{ResellerID}:{ConnMerchantID}` / `{ResellerAPIKey}`
- **Amounts**: Always in cents (int)
- **PHP**: 8.2+ strict types
- **ISV orders**: `/checkout/v2/isv/orders?MerchantId=` — camelCase, NO sourceCode
- **ISV transactions**: Legacy API Composite Basic Auth — PascalCase
- **POS terminal**: `/ecr/isv/v1/` — Bearer. Preauth NOT supported.
- **Native Checkout ISV**: `/nativecheckout/v2/isv/` — Bearer. Charge tokens + transactions.
- **ISV Webhooks (ISV-level)**: `/isv/v1/webhooks` — Bearer. CRUD management. Events auto-broadcast (1796/1797/1798/1799/8193/8194).
- **Merchant Webhooks (merchant-level)**: `/api/messages/config` — Composite Auth. Per-merchant registration (768/769/2054).
- **ISV Accounts**: `/isv/v1/accounts` — Bearer. ISV-specific account management.
- **Webhook events**: 21 event types. `Webhooks::EVENTS` constant + `Webhooks::isKnownEvent()`.
- **Environment helpers**: `$isv->getConfig()->isProduction()` / `isSandbox()`

## Merchant Webhook Registration Example

```php
// ALWAYS use merchantWebhookRegistrar() — idempotent, handles 400 duplicate silently
$results = $isv->merchantWebhookRegistrar()->registerAll(
    connectedMerchantId: $merchantId,
    callbackUrl: 'https://app.example.com/api/webhooks/viva',
);
// $results: [['event_id' => 768, 'status' => 'created|already_exists|failed'], ...]

// AVOID calling IsvMessages::register() directly in provisioning flows
// (HTTP 400 on duplicate would break the flow)
```
