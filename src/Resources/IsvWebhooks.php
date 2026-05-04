<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Webhook management via /isv/v1/webhooks.
 *
 * Create webhooks for ISV-level events. Uses Bearer ISV OAuth token (New API).
 *
 * **ISV-level events delivered through this resource (multi-merchant fan-out):**
 *   - 1796 — TransactionPaymentCreated
 *   - 1797 — TransactionReversalCreated (refund)
 *   - 1798 — TransactionFailed
 *   - 1799 — TransactionPriceCalculated
 *   - 8193 — AccountConnected (KYB completion)
 *   - 8194 — AccountVerificationStatusChanged
 *
 * **Merchant-level events (768/769/2054)** must be registered through
 * {@see IsvMessages} — they are NOT broadcast on the ISV channel.
 *
 * @see https://developer.viva.com/isv-partner-program/payment-isv-api/#tag/Webhooks
 * @see prod-findings.md
 */
final class IsvWebhooks
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Create a webhook subscription for an ISV event.
     *
     * Note: Viva's API expects `eventTypeId` (numeric ID), NOT `eventType` (string
     * name) despite some doc samples. The SDK accepts the int directly.
     *
     * @param  string  $url  The URL to receive webhook notifications. Must be HTTPS.
     *                       Viva will issue a GET to this URL during registration
     *                       expecting a JSON body `{"Key": "<verification-key>"}`
     *                       (see {@see verificationToken()}).
     * @param  int  $eventTypeId  Numeric event id (1796, 1797, 1798, 1799, 8193, 8194)
     * @return array<string, mixed>
     */
    public function create(string $url, int $eventTypeId): array
    {
        return $this->http->post('/isv/v1/webhooks', [
            'url'         => $url,
            'eventTypeId' => $eventTypeId,
        ]);
    }

    /**
     * Fetch the verification key handshake for the ISV webhook callback URL.
     *
     * Call this BEFORE registering any webhook via {@see create()}. The
     * returned key must be:
     *  1. Persisted application-side as `webhook_verification_key`
     *  2. Used to verify the HMAC-SHA256 signature on incoming webhooks
     *     (header `X-Viva-Signature`)
     *  3. Echoed by your `GET /api/webhooks/viva` endpoint as
     *     `{"Key": "<verification-key>"}` — Viva calls this URL during
     *     webhook registration to confirm you control the callback.
     *
     * @return array{Key?: string, key?: string}
     */
    public function verificationToken(): array
    {
        return $this->http->get('/isv/v1/webhooks/token');
    }

    /**
     * List all ISV webhooks registered for this ISV partner.
     *
     * ⚠️ **Production gotcha** — Viva does NOT expose a GET endpoint for ISV
     * webhooks listing; this call returns HTTP 405. Track registered
     * EventTypeIds application-side instead (e.g. admin settings, log).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \QrCommunication\VivaIsv\Exceptions\ApiException Always (HTTP 405)
     */
    public function list(): array
    {
        return $this->http->get('/isv/v1/webhooks');
    }

    /**
     * Delete an ISV webhook.
     *
     * @param  string  $webhookId  The webhook UUID to delete
     * @return array<string, mixed>
     */
    public function delete(string $webhookId): array
    {
        return $this->http->delete("/isv/v1/webhooks/{$webhookId}");
    }

    /**
     * Update an ISV webhook.
     *
     * @param  string  $webhookId  The webhook UUID to update
     * @param  string  $url  New URL for the webhook
     * @param  int|null  $eventTypeId  New event type id (null = keep current)
     * @return array<string, mixed>
     */
    public function update(string $webhookId, string $url, ?int $eventTypeId = null): array
    {
        return $this->http->put("/isv/v1/webhooks/{$webhookId}", array_filter([
            'url'         => $url,
            'eventTypeId' => $eventTypeId,
        ], fn ($v) => $v !== null));
    }
}
