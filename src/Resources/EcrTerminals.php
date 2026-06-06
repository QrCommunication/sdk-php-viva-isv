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
     * Refund (cancel) a previous ISV sale on a POS terminal.
     *
     * POST /ecr/isv/v1/transactions:refund — referenced refund tied to the
     * original sale's `parentSessionId`. A fresh `sessionId` is generated for
     * the refund operation itself (auto-generated when null).
     *
     * @param  string  $sessionId  The parent (original sale) session UUID to refund
     * @param  int  $amount  Amount to refund in cents
     * @param  string|null  $merchantReference  Internal reference (defaults to refund session id)
     * @param  int  $terminalId  Terminal TID (e.g. 16014231)
     * @param  string  $terminalMerchantId  Merchant UUID that owns the terminal
     * @param  string  $cashRegisterId  ECR identifier
     * @param  int  $currencyCode  ISO 4217 numeric (978 = EUR)
     * @param  string|null  $refundSessionId  UUID for the refund itself (auto-generated if null)
     * @return array{session_id: string, success: bool}
     */
    public function refund(
        string $sessionId,
        int $amount,
        ?string $merchantReference = null,
        int $terminalId = 0,
        string $terminalMerchantId = '',
        string $cashRegisterId = 'SDK-CR1',
        int $currencyCode = 978,
        ?string $refundSessionId = null,
    ): array {
        $refundSessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $refundSessionId,
            'parentSessionId' => $sessionId,
            'terminalId' => $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => (string) $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-refund-'.$refundSessionId,
        ], fn ($v) => $v !== '' && $v !== 0);

        $payload['isvDetails'] = ['terminalMerchantId' => $terminalMerchantId];

        $this->http->post('/ecr/isv/v1/transactions:refund', $payload);

        return [
            'session_id' => $refundSessionId,
            'success' => true,
        ];
    }

    /**
     * Create an action request to be invoked on a POS device.
     *
     * POST /ecr/isv/v1/actions — e.g. an `aade-fim-control` action. The payload
     * is passed through as-is (camelCase, New API convention) and must include
     * `terminalId`, `cashRegisterId`, `isvDetails.terminalMerchantId` and `request`.
     *
     * @param  array<string, mixed>  $payload  Action request body
     * @return array{terminalId?: string, cashRegisterId?: string, actionType?: string, actionId?: string}
     */
    public function createAction(array $payload): array
    {
        return $this->http->post('/ecr/isv/v1/actions', $payload);
    }

    /**
     * Fetch the result of a previously created action.
     *
     * GET /ecr/isv/v1/actions/{actionId}. Returns HTTP 202 (empty body → []) while
     * the action is still processing, or the action result once completed.
     *
     * @return array<string, mixed>  Action data with successfullyProcessed, response, etc.
     */
    public function getAction(string $actionId): array
    {
        return $this->http->get("/ecr/isv/v1/actions/{$actionId}");
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
