# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.6.0] - 2026-05-04

### Added

- **`Resources\IsvAccounts::getOnboardingUrl(string $accountId): ?string`** —
  Retourne `invitation.redirectUrl` du compte connecté. Réplique
  `ConnectedAccounts::onboardingUrl()` mais via `/isv/v1/` qui marche en prod
  ISV (vs `/platforms/v1/` qui retourne 403 sur ISV pure).

- **`Resources\IsvAccounts::isVerified(string $accountId): bool`** — Check
  KYB validation. Réplique `ConnectedAccounts::isVerified()` via `/isv/v1/`.

- **`Resources\IsvAccounts::isAcquiringEnabled(string $accountId): bool`** —
  Check si le compte peut déjà accepter des paiements (souvent `true` avant
  `verified` car KYB submitted mais Viva encore en review).

- **`Resources\IsvWebhooks::verificationToken(): array`** — Endpoint
  `GET /isv/v1/webhooks/token` pour récupérer la verification key avant
  enregistrement. La clé sert à signer les webhooks entrants (HMAC-SHA256
  via header `X-Viva-Signature`).

- **`Helpers\MerchantWebhookRegistrar` — nouveau status `endpoint_unavailable`** —
  Distingue le 404 "endpoint déprécié" (cas typique prod ISV) des autres
  failures. Plus 3 helpers statiques : `allSucceeded()`, `hasEndpointIssue()`,
  `allFailed()`.

- **`skill/references/prod-findings.md`** — Référence consolidée des
  comportements observés en production sur compte ISV : endpoints qui ne
  fonctionnent pas (405/403/404), workarounds SDK-natifs, OAuth split
  ISV/Smart Checkout, pièges UX onboarding, signature handshake.

- **Section "Production gotchas"** ajoutée à `skill/SKILL.md` — référence
  rapide des pièges Viva non documentés et des configs à demander au
  support Viva pour démarrer une intégration ISV.

### Changed

- **`Resources\IsvWebhooks::create()` — signature corrigée** : `$eventTypeId: int`
  au lieu de `$eventType: string`. L'API Viva attend un numérique
  (`1796/1797/1798/1799/8193/8194`), pas un nom d'event. Breaking pour les
  consommateurs qui passaient un string — mais l'ancienne signature ne
  fonctionnait pas en prod (Viva rejetait avec eventId 3732 ou similaire).

- **`Resources\IsvWebhooks::update()`** : `$eventTypeId: ?int` (cohérence avec
  `create()`).

- **`Resources\ConnectedAccounts` doc enrichie** — avertissement
  HTTP 403 sur prod ISV + table de mapping vers `IsvAccounts`.

- **`Resources\IsvMessages` doc enrichie** — avertissement HTTP 404 sur
  `/api/messages/config` en prod ISV + workaround polling reconciliation.

- **`Resources\IsvAccounts::list()` doc** — avertissement HTTP 405.

- **`Resources\IsvWebhooks::list()` doc** — avertissement HTTP 405.

### Notes

Cette version regroupe toutes les corrections d'inadéquation entre la doc
Viva et les comportements API réels en production observés sur un compte
ISV (Merchant ID `0119432c-...` / Reseller `a9a47392-...`) lors d'une
session de debug 2026-05-04. Aucun bug critique du SDK — uniquement des
ajouts de méthodes utiles, des fixes de signatures, et une documentation
exhaustive des limitations API.

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
