# Viva Wallet ISV SDK — Agent Instructions

See [CLAUDE.md](CLAUDE.md) for complete SDK architecture, patterns, and implementation guidelines.

## Quick Reference

- **Package**: `qrcommunication/viva-isv-sdk`
- **Namespace**: `QrCommunication\VivaIsv\`
- **Entry point**: `VivaIsvClient`
- **Resources**: 10 (`accounts`, `isvAccounts`, `orders`, `transactions`, `terminals`, `transfers`, `marketplace`, `nativeCheckout`, `isvWebhooks`, `webhooks`)
- **Pattern**: Resource pattern (`$isv->orders->create()`)
- **3 auth modes**: Bearer (ISV OAuth), Basic Auth (own merchant), Composite Basic Auth (connected merchants)
- **Composite Auth** (undocumented): `{ResellerID}:{ConnMerchantID}` / `{ResellerAPIKey}`
- **Amounts**: Always in cents (int)
- **PHP**: 8.2+ strict types
- **ISV orders**: `/checkout/v2/isv/orders?MerchantId=` — camelCase, NO sourceCode
- **ISV transactions**: Legacy API Composite Basic Auth — PascalCase
- **POS terminal**: `/ecr/isv/v1/` — Bearer. Preauth NOT supported.
- **Native Checkout ISV**: `/nativecheckout/v2/isv/` — Bearer. Charge tokens + transactions.
- **ISV Webhooks**: `/isv/v1/webhooks` — Bearer. CRUD management.
- **ISV Accounts**: `/isv/v1/accounts` — Bearer. ISV-specific account management.
- **Webhook events**: 21 event types. `Webhooks::EVENTS` constant + `Webhooks::isKnownEvent()`.
