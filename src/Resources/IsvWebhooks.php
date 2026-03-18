<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Webhook management via /isv/v1/webhooks.
 *
 * Create, list, update and delete webhooks for ISV events.
 * Uses Bearer ISV OAuth token (New API).
 */
final class IsvWebhooks
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Create a webhook for ISV events.
     *
     * @param  string  $url  The URL to receive webhook notifications
     * @param  string  $eventType  The event type to subscribe to (e.g. 'transaction.payment.created')
     * @param  string|null  $description  Human-readable description
     * @return array<string, mixed>  Created webhook with webhookId
     */
    public function create(string $url, string $eventType, ?string $description = null): array
    {
        return $this->http->post('/isv/v1/webhooks', array_filter([
            'url' => $url,
            'eventType' => $eventType,
            'description' => $description,
        ]));
    }

    /**
     * List all ISV webhooks.
     *
     * @return array<int, array<string, mixed>>
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
     * @param  string|null  $eventType  New event type (null = keep current)
     * @return array<string, mixed>
     */
    public function update(string $webhookId, string $url, ?string $eventType = null): array
    {
        return $this->http->put("/isv/v1/webhooks/{$webhookId}", array_filter([
            'url' => $url,
            'eventType' => $eventType,
        ]));
    }
}
