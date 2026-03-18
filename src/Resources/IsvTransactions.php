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
