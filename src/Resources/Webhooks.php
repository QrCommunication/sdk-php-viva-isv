<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

/**
 * ISV Webhook verification and parsing.
 *
 * Same as merchant webhooks but includes ISV-specific events
 * like POS ECR session events.
 */
final class Webhooks
{
    /**
     * Handle the webhook verification GET request.
     *
     * @return array{StatusCode: int, Key: string}
     */
    public function verificationResponse(string $verificationKey): array
    {
        return [
            'StatusCode' => 0,
            'Key' => $verificationKey,
        ];
    }

    /**
     * Parse a webhook POST payload.
     *
     * @return array{event_type: string, event_data: array<string, mixed>}
     */
    public function parse(string $rawBody): array
    {
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid webhook payload');
        }

        return [
            'event_type' => $this->resolveEventType($data['EventTypeId'] ?? 0),
            'event_data' => $data['EventData'] ?? $data,
        ];
    }

    private function resolveEventType(int $eventTypeId): string
    {
        return match ($eventTypeId) {
            1796 => 'transaction.payment.created',
            1797 => 'transaction.refund.created',
            1798 => 'transaction.payment.cancelled',
            1799 => 'transaction.reversal.created',
            1800 => 'transaction.preauth.created',
            1801 => 'transaction.preauth.completed',
            1802 => 'transaction.preauth.cancelled',
            1810 => 'pos.session.created',
            1811 => 'pos.session.failed',
            default => "unknown.{$eventTypeId}",
        };
    }
}
