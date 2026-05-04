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
 *     // Each entry: ['event_id' => 768, 'status' => 'created'|'already_exists'|'endpoint_unavailable'|'failed']
 *
 * **Status reference (post-2026-05 update):**
 *   - `created` — webhook was registered successfully
 *   - `already_exists` — duplicate (HTTP 400 + duplicate marker)
 *   - `endpoint_unavailable` — `/api/messages/config` returned HTTP 404 on
 *     this Viva merchant. The API is deprecated/restricted for many ISV
 *     accounts (see {@see IsvMessages} class doc). Treat as a soft failure
 *     and rely on a polling reconciliation job for these events.
 *   - `failed` — any other error (auth, network, 5xx). Caller should retry.
 *
 * Result helpers: {@see allSucceeded()}, {@see hasEndpointIssue()},
 * {@see allFailed()}.
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
     * @return array<int, array{event_id: int, status: 'created'|'already_exists'|'endpoint_unavailable'|'failed', message?: string}>
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
                } elseif ($this->isEndpointUnavailable($e)) {
                    $results[] = [
                        'event_id' => $eventId,
                        'status'   => 'endpoint_unavailable',
                        'message'  => $e->getMessage(),
                    ];
                } else {
                    $results[] = ['event_id' => $eventId, 'status' => 'failed', 'message' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    /**
     * Whether every event in the result set was registered (created or already existed).
     *
     * @param  array<int, array{status: string}>  $results
     */
    public static function allSucceeded(array $results): bool
    {
        foreach ($results as $r) {
            if (! in_array($r['status'] ?? '', ['created', 'already_exists'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether all events failed because Viva returned 404 (endpoint deprecated
     * or not enabled on this ISV merchant). Use this to short-circuit retries
     * and fall back to a polling reconciliation job.
     *
     * @param  array<int, array{status: string}>  $results
     */
    public static function hasEndpointIssue(array $results): bool
    {
        if (empty($results)) {
            return false;
        }

        foreach ($results as $r) {
            if (($r['status'] ?? '') !== 'endpoint_unavailable') {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether every event failed (any status that is not created/already_exists).
     *
     * @param  array<int, array{status: string}>  $results
     */
    public static function allFailed(array $results): bool
    {
        if (empty($results)) {
            return false;
        }

        foreach ($results as $r) {
            if (in_array($r['status'] ?? '', ['created', 'already_exists'], true)) {
                return false;
            }
        }

        return true;
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

    /**
     * Whether the exception indicates that the `/api/messages/config` endpoint
     * is unavailable on this Viva merchant (HTTP 404 — typically because the
     * endpoint is deprecated or not enabled for this ISV partner).
     *
     * Distinguishes from generic auth or network failures so callers can
     * fall back to polling-based reconciliation instead of retrying.
     */
    private function isEndpointUnavailable(ApiException $e): bool
    {
        if ($e->httpStatus !== 404) {
            return false;
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'no http resource')
            || str_contains($msg, 'not found')
            || str_contains($msg, '/api/messages/config');
    }
}
