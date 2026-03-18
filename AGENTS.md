# Viva Wallet ISV SDK — Agent Instructions

See [CLAUDE.md](CLAUDE.md) for complete SDK architecture, patterns, and implementation guidelines.

## Quick Reference

- **Package**: `qrcommunication/viva-isv-sdk`
- **Namespace**: `QrCommunication\VivaIsv\`
- **Entry point**: `VivaIsvClient`
- **Pattern**: Resource pattern (`$isv->orders->create()`)
- **3 auth modes**: Bearer (ISV OAuth), Basic Auth (own merchant), Composite Basic Auth (connected merchants)
- **Composite Auth** (undocumented): `{ResellerID}:{ConnMerchantID}` / `{ResellerAPIKey}`
- **Amounts**: Always in cents (int)
- **PHP**: 8.2+ strict types
- **ISV orders**: `/checkout/v2/isv/orders?MerchantId=` — camelCase, NO sourceCode
- **ISV transactions**: Legacy API Composite Basic Auth — PascalCase
- **POS terminal**: `/ecr/isv/v1/` — Bearer. Preauth NOT supported.
