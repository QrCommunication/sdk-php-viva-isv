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
