<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;
use QrCommunication\VivaIsv\IsvConfig;

/**
 * Manages merchant-level webhook subscriptions via /api/messages/config.
 *
 * Uses Composite Basic Auth (ResellerID:ConnectedMerchantID + ResellerAPIKey).
 * Endpoint lives on the Legacy API host (www.vivapayments.com in production,
 * demo.vivapayments.com in sandbox), not on api.vivapayments.com.
 *
 * Events useful to register per-merchant:
 *  - 768  : Command Bank Transfer Created (emitted bank transfer created)
 *  - 769  : Command Bank Transfer Executed (emitted bank transfer executed)
 *  - 2054 : Account Transaction Created (settlements / received funds)
 *
 * Note: events 1796/1797/1798/1799/8193/8194 do NOT belong here — they are
 * auto-broadcast to ISV-level webhooks via /isv/v1/webhooks.
 *
 * ⚠️ **Production gotcha (2026-05)** — `/api/messages/config` returns HTTP 404
 * on every Viva host tested (`www.vivapayments.com`, `api.vivapayments.com`,
 * `demo.vivapayments.com`) for ISV-managed merchants. The endpoint appears
 * **deprecated or restricted** for the ISV path. All `register()` / `list()` /
 * `delete()` calls will throw {@see ApiException} HTTP 404 with body
 * `"No HTTP resource was found that matches the request URI..."`.
 *
 * **Workaround**: poll settlements / transfers via wallet API on a cron job
 * (e.g. hourly reconciliation) — see {@see Transfers} and the merchant SDK's
 * `Wallets::searchTransactions()`. The events themselves are not lost — they
 * are persisted in Viva's wallet ledger and retrievable via these reads.
 *
 * If you need real-time delivery for these events, ask Viva support to
 * "enable merchant-level webhook registration via /api/messages/config on
 * our ISV merchant" — frequently denied, hence the polling fallback.
 *
 * @see prod-findings.md
 * @see Transfers
 */
final class IsvMessages
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly IsvConfig $config,
    ) {}

    /**
     * Register a webhook subscription for a connected merchant.
     *
     * Idempotent in practice: if a webhook already exists with the same
     * URL + EventTypeId, Viva returns HTTP 400 indicating duplicate.
     * Use MerchantWebhookRegistrar to handle that case transparently.
     *
     * Params are PascalCase (Legacy API convention).
     *
     * @return array<string, mixed> Decoded JSON response (MessageId, EventTypeId, Url, IsActive)
     */
    public function register(string $connectedMerchantId, int $eventTypeId, string $callbackUrl): array
    {
        return $this->http->compositePost('/api/messages/config', [
            'Url' => $callbackUrl,
            'EventTypeId' => $eventTypeId,
            'MessageTypeId' => 0,
            'IsActive' => true,
        ], $connectedMerchantId);
    }

    /**
     * List all webhook subscriptions for a connected merchant.
     *
     * @return array<string, mixed>
     */
    public function list(string $connectedMerchantId): array
    {
        return $this->http->compositeGet('/api/messages/config', $connectedMerchantId);
    }

    /**
     * Delete a webhook subscription by its MessageId.
     *
     * @return array<string, mixed>
     */
    public function delete(string $connectedMerchantId, string $messageId): array
    {
        return $this->http->compositeDeleteUrl(
            $this->config->legacyUrl().'/api/messages/config/'.$messageId,
            $connectedMerchantId,
        );
    }
}
