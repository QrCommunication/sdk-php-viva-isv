<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\HttpClient;
use QrCommunication\VivaIsv\IsvConfig;

/**
 * ISV Transaction operations on connected merchant accounts.
 *
 * ALL operations use Composite Basic Auth on the Legacy API:
 *   Username: {ResellerID}:{ConnectedMerchantID}
 *   Password: {ResellerAPIKey}
 *
 * This auth format is NOT documented by Viva Wallet.
 * Params are PascalCase (Legacy API convention).
 *
 * Prerequisite: "Allow recurring payments and pre-auth captures via API"
 * must be enabled in ISV account Settings > API Access.
 */
final class IsvTransactions
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly IsvConfig $config,
    ) {}

    /**
     * Get transaction details for a connected merchant.
     *
     * @return array<string, mixed>  Raw Viva transaction data
     */
    public function get(string $transactionId, string $connectedMerchantId): array
    {
        return $this->http->compositeGet(
            "/api/transactions/{$transactionId}",
            $connectedMerchantId,
        );
    }

    /**
     * List transactions for a connected merchant on a given date.
     *
     * @param  string  $date  Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    public function listByDate(string $connectedMerchantId, string $date): array
    {
        $result = $this->http->compositeGet(
            '/api/transactions',
            $connectedMerchantId,
            ['date' => $date],
        );

        return $result['Transactions'] ?? [];
    }

    /**
     * List transactions for a connected merchant by clearance date.
     *
     * GET /api/transactions/?clearancedate={date} (Composite Basic Auth).
     *
     * @param  string  $clearanceDate  Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    public function listByClearanceDate(string $connectedMerchantId, string $clearanceDate): array
    {
        $result = $this->http->compositeGet(
            '/api/transactions',
            $connectedMerchantId,
            ['clearancedate' => $clearanceDate],
        );

        return $result['Transactions'] ?? [];
    }

    /**
     * List transactions for a connected merchant by order code.
     *
     * GET /api/transactions/?ordercode={orderCode} (Composite Basic Auth).
     *
     * @param  int  $orderCode  The order code to filter on
     * @return array<int, array<string, mixed>>
     */
    public function listByOrderCode(string $connectedMerchantId, int $orderCode): array
    {
        $result = $this->http->compositeGet(
            '/api/transactions',
            $connectedMerchantId,
            ['ordercode' => $orderCode],
        );

        return $result['Transactions'] ?? [];
    }

    /**
     * List transactions for a connected merchant by source code (and date).
     *
     * GET /api/transactions/?sourcecode={sourceCode}&date={date} (Composite Basic Auth).
     *
     * @param  string  $sourceCode  Payment source code to filter on
     * @param  string  $date  Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    public function listBySourceCode(string $connectedMerchantId, string $sourceCode, string $date): array
    {
        $result = $this->http->compositeGet(
            '/api/transactions',
            $connectedMerchantId,
            ['sourcecode' => $sourceCode, 'date' => $date],
        );

        return $result['Transactions'] ?? [];
    }

    /**
     * Make a MOTO (Mail Order / Telephone Order) card charge for a connected merchant.
     *
     * POST /api/transactions on the Legacy API with Composite Basic Auth.
     * The payload is passed through as-is (camelCase, per Viva MOTO spec) and
     * must include `moto: true`, `creditcard` and `orderCode`. The `moto` flag
     * is forced to true if absent.
     *
     * @param  array<string, mixed>  $payload  MOTO charge body
     * @return array<string, mixed>  Transaction result (TransactionId, StatusId, etc.)
     */
    public function moto(string $connectedMerchantId, array $payload): array
    {
        $payload['moto'] ??= true;

        return $this->http->compositePost(
            '/api/transactions',
            $payload,
            $connectedMerchantId,
        );
    }

    /**
     * Increase the amount of an existing ISV pre-authorized transaction.
     *
     * POST /acquiring/v1/isv/transactions/{id}:increasepreauth on the New API
     * (Bearer ISV token, camelCase body). The merchant is identified via the
     * `merchantId` query param.
     *
     * @param  string  $transactionId  The initial pre-auth transaction UUID
     * @param  string  $connectedMerchantId  UUID of the merchant the transaction belongs to
     * @param  int  $amount  Amount to add to the original authorization, in cents
     * @param  string|null  $customerDescription  Description shown to the customer
     * @param  string|null  $merchantReference  Internal reference
     * @param  string|null  $sourceCode  Payment source code
     * @param  int|null  $currencyCode  ISO 4217 numeric (978 = EUR)
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return array{transactionId?: string}
     */
    public function increasePreauth(
        string $transactionId,
        string $connectedMerchantId,
        int $amount,
        ?string $customerDescription = null,
        ?string $merchantReference = null,
        ?string $sourceCode = null,
        ?int $currencyCode = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = array_filter([
            'amount' => $amount,
            'customerTrns' => $customerDescription,
            'merchantTrns' => $merchantReference,
            'sourceCode' => $sourceCode,
            'currencyCode' => $currencyCode !== null ? (string) $currencyCode : null,
            'idempotencyKey' => $idempotencyKey,
        ], fn ($v) => $v !== null);

        return $this->http->post(
            "/acquiring/v1/isv/transactions/{$transactionId}:increasepreauth?merchantId={$connectedMerchantId}",
            $payload,
        );
    }

    /**
     * Capture a pre-authorized transaction.
     *
     * @param  string  $transactionId  The preauth transaction UUID
     * @param  int  $amount  Amount in cents to capture
     * @param  int|null  $isvAmount  ISV fee in cents (included in capture)
     * @return array<string, mixed>
     */
    public function capture(string $transactionId, string $connectedMerchantId, int $amount, ?int $isvAmount = null): array
    {
        $payload = ['Amount' => $amount];
        if ($isvAmount !== null) {
            $payload['IsvAmount'] = $isvAmount;
        }

        $result = $this->http->compositePost(
            "/api/transactions/{$transactionId}",
            $payload,
            $connectedMerchantId,
        );

        if (($result['ErrorCode'] ?? -1) !== 0) {
            throw new ApiException(
                $result['ErrorText'] ?? 'Capture failed',
                400,
                $result,
            );
        }

        return $result;
    }

    /**
     * Charge a recurring payment on a connected merchant.
     *
     * @param  string  $initialTransactionId  The initial transaction UUID (card token)
     * @param  int  $amount  Amount in cents
     * @param  int|null  $isvAmount  ISV fee in cents
     * @param  string|null  $sourceCode  Payment source code
     * @return array<string, mixed>
     */
    public function recurring(
        string $initialTransactionId,
        string $connectedMerchantId,
        int $amount,
        ?int $isvAmount = null,
        ?string $sourceCode = null,
    ): array {
        $payload = ['Amount' => $amount];
        if ($isvAmount !== null) {
            $payload['IsvAmount'] = $isvAmount;
        }
        if ($sourceCode !== null) {
            $payload['SourceCode'] = $sourceCode;
        }

        $result = $this->http->compositePost(
            "/api/transactions/{$initialTransactionId}",
            $payload,
            $connectedMerchantId,
        );

        if (($result['ErrorCode'] ?? -1) !== 0) {
            throw new ApiException(
                $result['ErrorText'] ?? 'Recurring charge failed',
                400,
                $result,
            );
        }

        return $result;
    }

    /**
     * Cancel or refund a transaction on a connected merchant.
     *
     * @param  int|null  $amount  Amount in cents (null = full refund)
     * @param  string|null  $sourceCode  Payment source code
     * @return array<string, mixed>
     */
    public function cancel(
        string $transactionId,
        string $connectedMerchantId,
        ?int $amount = null,
        ?string $sourceCode = null,
    ): array {
        $params = array_filter([
            'amount' => $amount,
            'sourceCode' => $sourceCode,
        ]);

        $url = $this->config->legacyUrl()."/api/transactions/{$transactionId}";
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $this->http->compositeDeleteUrl($url, $connectedMerchantId);
    }
}
