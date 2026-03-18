<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;
use QrCommunication\VivaIsv\IsvConfig;

/**
 * Cloud Terminal API — ISV POS terminal operations.
 *
 * Uses /ecr/isv/v1/ endpoints with Bearer ISV token.
 * The ISV endpoints differ from merchant endpoints (/ecr/v1/):
 * - Sale requires `isvDetails` with `amount` and `terminalMerchantId`
 * - Preauth is NOT supported via ISV Cloud Terminal (use Smart Checkout)
 * - Sessions use /ecr/isv/v1/sessions/ (not /ecr/v1/sessions/)
 * - Abort uses GET (not DELETE) with cashRegisterId as query param
 */
final class EcrTerminals
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly IsvConfig $config,
    ) {}

    /**
     * Search POS devices for a merchant.
     *
     * @return array<int, array<string, mixed>>  List of terminals
     */
    public function search(?string $merchantId = null, ?int $statusId = null, ?string $sourceCode = null): array
    {
        return $this->http->post('/ecr/isv/v1/devices:search', array_filter([
            'merchantId' => $merchantId,
            'statusId' => $statusId,
            'sourceCode' => $sourceCode,
        ]));
    }

    /**
     * Create an ISV sale on a POS terminal.
     *
     * @param  int  $terminalId  Terminal TID (e.g. 16014231)
     * @param  int  $amount  Amount in cents
     * @param  int  $isvAmount  ISV fee in cents
     * @param  string  $terminalMerchantId  Merchant UUID that owns the terminal
     * @param  string  $cashRegisterId  ECR identifier (e.g. "PratiConnect-CR1")
     * @param  string|null  $merchantReference  Internal reference
     * @param  int  $currencyCode  ISO 4217 numeric (978 = EUR)
     * @param  string|null  $sessionId  UUID (auto-generated if null)
     * @return array{session_id: string, success: bool}
     */
    public function sale(
        int $terminalId,
        int $amount,
        int $isvAmount,
        string $terminalMerchantId,
        string $cashRegisterId = 'SDK-CR1',
        ?string $merchantReference = null,
        int $currencyCode = 978,
        ?string $sessionId = null,
    ): array {
        $sessionId ??= $this->generateUuid();

        $this->http->post('/ecr/isv/v1/transactions:sale', [
            'sessionId' => $sessionId,
            'terminalId' => $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-'.$sessionId,
            'tipAmount' => 0,
            'isvDetails' => [
                'amount' => $isvAmount,
                'terminalMerchantId' => $terminalMerchantId,
            ],
        ]);

        return [
            'session_id' => $sessionId,
            'success' => true,
        ];
    }

    /**
     * Poll a session to check the result.
     *
     * @return array<string, mixed>  Session data with success, eventId, transactionId, etc.
     */
    public function getSession(string $sessionId): array
    {
        return $this->http->get("/ecr/isv/v1/sessions/{$sessionId}");
    }

    /**
     * List sessions for a date.
     *
     * @param  string  $date  Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(string $date): array
    {
        return $this->http->get('/ecr/isv/v1/sessions', ['date' => $date]);
    }

    /**
     * Abort an active session.
     *
     * Uses GET (not DELETE) with cashRegisterId as query param.
     *
     * @return array<string, mixed>
     */
    public function abort(string $sessionId, string $cashRegisterId): array
    {
        return $this->http->get(
            "/ecr/isv/v1/sessions:abort/{$sessionId}",
            ['cashRegisterId' => $cashRegisterId],
        );
    }

    /**
     * Poll a session until it reaches a terminal state or timeout.
     *
     * @param  int  $timeoutSeconds  Max wait time
     * @param  int  $intervalMs  Polling interval in milliseconds
     * @return array<string, mixed>  Final session state
     */
    public function pollUntilComplete(string $sessionId, int $timeoutSeconds = 120, int $intervalMs = 3000): array
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $session = $this->getSession($sessionId);

            $eventId = $session['eventId'] ?? null;

            // eventId 1100 = still processing, keep polling
            if ($eventId !== 1100) {
                return $session;
            }

            usleep($intervalMs * 1000);
        }

        return $session ?? ['success' => false, 'eventId' => 1003, 'message' => 'SDK poll timeout'];
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        );
    }
}
