# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.5.0] - 2026-05-01

### Added

- **`Resources\IsvMessages`** (`src/Resources/IsvMessages.php`) — registration des merchant-level
  webhooks via `/api/messages/config` (Composite Basic Auth). Provides `register()`, `list()` and
  `delete()` methods. Exposed as `$isv->messages` on `VivaIsvClient`. Covers banking events
  768 / 769 / 2054 which must be registered per-merchant (not covered by ISV-level webhooks).

- **`Helpers\MerchantWebhookRegistrar`** (`src/Helpers/MerchantWebhookRegistrar.php`) — helper
  idempotent au-dessus d'`IsvMessages`. Traite HTTP 400 "duplicate" comme succes silencieux.
  Constante `BANKING_EVENTS = [768, 769, 2054]` alignee avec `viva-merchant-sdk` v1.4.0.
  Expose via `$isv->merchantWebhookRegistrar()` (lazy-initialized).

- **`IsvConfig::isProduction()`** — retourne `true` si le client est configure pour la production.

- **`IsvConfig::isSandbox()`** — retourne `true` si le client est configure pour la demo/sandbox.

- **`HttpClient` injectable `$guzzle` parameter** — `?Client $guzzle = null` en constructeur pour
  injecter un `MockHandler` Guzzle dans les tests. Backward-compatible.

- **Tests** — `tests/Unit/Resources/IsvMessagesTest.php` (5 tests / 23 assertions),
  `tests/Unit/Helpers/MerchantWebhookRegistrarTest.php` (7 tests / 47 assertions), plus
  `tests/Fakes/GuzzleMockFactory.php`. Total : 12/12 PASS, 70 assertions.

- **Documentation** — README sections "11. IsvMessages" + "Merchant-Level Webhook Registration" +
  "IsvConfig helpers" ; CLAUDE.md + AGENTS.md architecture map ; `skill/SKILL.md` section
  "Webhooks merchant-level" ; `openapi.yaml` tag `IsvMessages` + paths
  `/IsvMessages/register|list|delete` + schema `compositeBasicAuth`.

### Notes

- Aligne avec `qrcommunication/viva-merchant-sdk` v1.4.0 (constante `BANKING_EVENTS` commune).
- Zero breaking change — toutes les signatures publiques existantes sont preservees.

---

## [1.3.5] — 2026-03-18

Initial public release with 10 resources covering connected accounts, ISV accounts, ISV orders,
transactions (capture/recurring/cancel), Cloud Terminal POS, transfers, marketplace orders,
Native Checkout ISV, ISV webhooks CRUD and webhook parsing (21 events).
