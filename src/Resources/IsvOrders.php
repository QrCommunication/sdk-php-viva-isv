<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\HttpClient;
use QrCommunication\VivaIsv\IsvConfig;

/**
 * ISV Payment Orders — Smart Checkout for connected merchants.
 *
 * Uses POST /checkout/v2/isv/orders?MerchantId=<UUID> with Bearer ISV token.
 * IMPORTANT: camelCase params, NO sourceCode (connected merchant uses default).
 */
final class IsvOrders
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly IsvConfig $config,
    ) {}

    /**
     * Create an ISV order for a connected merchant.
     *
     * @param  string  $connectedMerchantId  UUID of the connected merchant
     * @param  int  $amount  Total amount in cents
     * @param  int  $isvAmount  ISV commission in cents (must be <= amount)
     * @param  string|null  $customerDescription  Description shown in checkout
     * @param  string|null  $merchantReference  Internal reference
     * @param  bool  $allowRecurring  Tokenize card for recurring
     * @param  bool  $preauth  Pre-authorize only
     * @return array{order_code: int, checkout_url: string}
     */
    public function create(
        string $connectedMerchantId,
        int $amount,
        int $isvAmount = 0,
        ?string $customerDescription = null,
        ?string $merchantReference = null,
        bool $allowRecurring = false,
        bool $preauth = false,
    ): array {
        if ($isvAmount > $amount) {
            throw new \InvalidArgumentException("isvAmount ({$isvAmount}) cannot exceed amount ({$amount})");
        }

        $payload = array_filter([
            'amount' => $amount,
            'customerTrns' => $customerDescription,
            'merchantTrns' => $merchantReference,
            'allowRecurring' => $allowRecurring,
            'preauth' => $preauth,
        ], fn ($v) => $v !== null);

        if ($isvAmount > 0) {
            $payload['isvAmount'] = $isvAmount;
        }

        $result = $this->http->post(
            "/checkout/v2/isv/orders?MerchantId={$connectedMerchantId}",
            $payload,
        );

        $orderCode = $result['orderCode'] ?? null;

        if (! $orderCode) {
            throw new ApiException('No orderCode returned', 400, $result);
        }

        return [
            'order_code' => $orderCode,
            'checkout_url' => $this->config->checkoutUrl().'?ref='.$orderCode,
        ];
    }

    /**
     * Retrieve a payment order for a connected merchant.
     *
     * GET /api/orders/{orderCode} on the Legacy API. Uses Composite Basic Auth
     * to read the order in the connected merchant's context.
     *
     * @param  int  $orderCode  The order code to retrieve
     * @param  string  $connectedMerchantId  UUID of the connected merchant
     * @return array<string, mixed>  Order data (OrderCode, StateId, RequestAmount, etc.)
     */
    public function retrieve(int $orderCode, string $connectedMerchantId): array
    {
        return $this->http->compositeGet(
            "/api/orders/{$orderCode}",
            $connectedMerchantId,
        );
    }

    /**
     * Cancel an open payment order for a connected merchant.
     *
     * DELETE /api/orders/{orderCode} on the Legacy API with Composite Basic Auth.
     *
     * @param  int  $orderCode  The order code to cancel
     * @param  string  $connectedMerchantId  UUID of the connected merchant
     * @return array<string, mixed>  Cancellation result (Success, ErrorCode, etc.)
     */
    public function cancel(int $orderCode, string $connectedMerchantId): array
    {
        $url = $this->config->legacyUrl()."/api/orders/{$orderCode}";

        return $this->http->compositeDeleteUrl($url, $connectedMerchantId);
    }

    /**
     * Get the Smart Checkout URL for an order code.
     */
    public function checkoutUrl(int $orderCode): string
    {
        return $this->config->checkoutUrl().'?ref='.$orderCode;
    }
}
