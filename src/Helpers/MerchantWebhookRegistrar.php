<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Helpers;

use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\Resources\IsvMessages;

/**
 * Idempotent merchant-level webhook registration helper.
 *
 * Wraps IsvMessages::register() to treat HTTP 400 "duplicate" responses
 * as success (already-registered), preventing noise in provisioning workflows.
 *
 * Usage:
 *
 *     $results = $isv->merchantWebhookRegistrar()->registerAll(
 *         connectedMerchantId: $merchantId,
 *         callbackUrl: 'https://app.example.com/api/webhooks/viva',
 *     );
 *     // Each entry: ['event_id' => 768, 'status' => 'created'|'already_exists'|'failed']
 */
final class MerchantWebhookRegistrar
{
    /**
     * Banking / transfer / settlement events recommended for a practitioner-style
     * use case (healthcare, wellness, etc.).
     *
     * - 768  : Command Bank Transfer Created
     * - 769  : Command Bank Transfer Executed
     * - 2054 : Account Transaction Created
     *
     * @var array<int, string>
     */
    public const BANKING_EVENTS = [
        768 => 'Command Bank Transfer Created',
        769 => 'Command Bank Transfer Executed',
        2054 => 'Account Transaction Created',
    ];

    public function __construct(private readonly IsvMessages $messages) {}

    /**
     * Register a set of events for a connected merchant.
     *
     * Idempotent: calling this multiple times with the same URL produces
     * 'already_exists' entries on subsequent calls, never failures.
     *
     * @param  array<int, string>|null  $events  Map eventId => label. Defaults to BANKING_EVENTS.
     * @return array<int, array{event_id: int, status: 'created'|'already_exists'|'failed', message?: string}>
     */
    public function registerAll(string $connectedMerchantId, string $callbackUrl, ?array $events = null): array
    {
        $events ??= self::BANKING_EVENTS;
        $results = [];

        foreach ($events as $eventId => $label) {
            try {
                $this->messages->register($connectedMerchantId, $eventId, $callbackUrl);
                $results[] = ['event_id' => $eventId, 'status' => 'created'];
            } catch (ApiException $e) {
                if ($this->isDuplicateError($e)) {
                    $results[] = ['event_id' => $eventId, 'status' => 'already_exists'];
                } else {
                    $results[] = ['event_id' => $eventId, 'status' => 'failed', 'message' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    /**
     * Determine whether an ApiException signals a duplicate subscription.
     *
     * Viva may return errorCode 3732 or include "duplicate" / "already" in the
     * error message body on HTTP 400 when the same URL+EventTypeId is registered twice.
     */
    private function isDuplicateError(ApiException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return $e->httpStatus === 400 && (
            str_contains($msg, 'duplicate')
            || str_contains($msg, 'already')
            || $e->getErrorCode() === 3732
        );
    }
}
