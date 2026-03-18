<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\HttpClient;
use QrCommunication\VivaIsv\IsvConfig;

/**
 * Marketplace Payment Orders — create orders with automatic seller transfers.
 *
 * Uses POST /checkout/v2/orders/ with Bearer ISV token.
 * The `transfer` parameter triggers automatic fund distribution.
 *
 * @see https://developer.viva.com/apis-for-payments/marketplace-api/
 */
final class MarketplaceOrders
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly IsvConfig $config,
    ) {}

    /**
     * Create a marketplace payment order with automatic seller transfer.
     *
     * @param  int  $amount  Total amount in cents
     * @param  string  $sellerAccountId  Connected seller account UUID
     * @param  int  $sellerAmount  Amount to transfer to seller in cents
     * @param  string|null  $customerDescription  Description for the customer
     * @param  string|null  $merchantReference  Internal reference
     * @param  string|null  $sourceCode  Payment source code
     * @param  bool  $preauth  Pre-authorize only
     * @return array{order_code: int, checkout_url: string}
     */
    public function create(
        int $amount,
        string $sellerAccountId,
        int $sellerAmount,
        ?string $customerDescription = null,
        ?string $merchantReference = null,
        ?string $sourceCode = null,
        bool $preauth = false,
    ): array {
        $platformFee = $amount - $sellerAmount;

        $payload = array_filter([
            'amount' => $amount,
            'customerTrns' => $customerDescription,
            'merchantTrns' => $merchantReference,
            'sourceCode' => $sourceCode,
            'preauth' => $preauth,
            'transfer' => [
                'targetAccountId' => $sellerAccountId,
                'amount' => $sellerAmount,
            ],
        ], fn ($v) => $v !== null);

        $result = $this->http->post('/checkout/v2/orders/', $payload);

        $orderCode = $result['orderCode'] ?? null;

        if (! $orderCode) {
            throw new ApiException('No orderCode returned', 400, $result);
        }

        return [
            'order_code' => $orderCode,
            'checkout_url' => $this->config->checkoutUrl().'?ref='.$orderCode,
            'platform_fee' => $platformFee,
        ];
    }

    /**
     * Cancel/refund a marketplace transaction with automatic transfer reversals.
     *
     * @param  string  $transactionId  Transaction UUID
     * @param  int|null  $amount  Amount in cents (null = full refund)
     * @param  bool  $reverseTransfers  Auto-reverse seller transfers
     * @param  bool  $refundPlatformFee  Refund the platform fee
     * @return array<string, mixed>
     */
    public function cancel(
        string $transactionId,
        ?int $amount = null,
        bool $reverseTransfers = true,
        bool $refundPlatformFee = false,
    ): array {
        $params = array_filter([
            'amount' => $amount,
            'reverseTransfers' => $reverseTransfers ? 'true' : null,
            'refundPlatformFee' => $refundPlatformFee ? 'true' : null,
        ]);

        $url = "/api/transactions/{$transactionId}";
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $this->http->delete($url);
    }
}
