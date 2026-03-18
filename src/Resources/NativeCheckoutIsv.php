<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Native Checkout — charge tokens + transactions for connected merchants.
 *
 * Uses /nativecheckout/v2/isv/ endpoints with Bearer ISV OAuth token.
 * Allows server-to-server payment processing without Smart Checkout redirect.
 *
 * Flow:
 * 1. Client collects card data → sends to Viva JS SDK → gets paymentData (encrypted)
 * 2. Server creates a charge token via createChargeToken()
 * 3. Server executes the transaction via createTransaction()
 */
final class NativeCheckoutIsv
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Generate a charge token for ISV native checkout.
     *
     * @param  string  $connectedMerchantId  UUID of the connected merchant
     * @param  int  $amount  Amount in cents
     * @param  string  $paymentData  Encrypted payment data from Viva JS SDK
     * @param  int  $paymentMethodId  Payment method ID (10 = card, default)
     * @return array{chargeToken: string}
     */
    public function createChargeToken(
        string $connectedMerchantId,
        int $amount,
        string $paymentData,
        int $paymentMethodId = 10,
    ): array {
        return $this->http->post(
            "/nativecheckout/v2/isv/chargetokens?MerchantId={$connectedMerchantId}",
            [
                'amount' => $amount,
                'paymentData' => $paymentData,
                'paymentMethodId' => $paymentMethodId,
            ],
        );
    }

    /**
     * Execute an ISV transaction with a charge token.
     *
     * @param  string  $connectedMerchantId  UUID of the connected merchant
     * @param  string  $chargeToken  Token from createChargeToken()
     * @param  int  $amount  Amount in cents
     * @param  int  $isvAmount  ISV commission in cents (must be <= amount)
     * @param  int  $currencyCode  ISO 4217 numeric (978 = EUR)
     * @param  string|null  $merchantTrns  Internal reference
     * @param  string|null  $customerTrns  Description shown to customer
     * @param  bool  $preauth  Pre-authorize only (do not capture)
     * @return array{transactionId: string, statusId: string}
     */
    public function createTransaction(
        string $connectedMerchantId,
        string $chargeToken,
        int $amount,
        int $isvAmount = 0,
        int $currencyCode = 978,
        ?string $merchantTrns = null,
        ?string $customerTrns = null,
        bool $preauth = false,
    ): array {
        if ($isvAmount > $amount) {
            throw new \InvalidArgumentException("isvAmount ({$isvAmount}) cannot exceed amount ({$amount})");
        }

        $payload = array_filter([
            'chargeToken' => $chargeToken,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantTrns' => $merchantTrns,
            'customerTrns' => $customerTrns,
            'preauth' => $preauth,
        ], fn ($v) => $v !== null);

        if ($isvAmount > 0) {
            $payload['isvAmount'] = $isvAmount;
        }

        return $this->http->post(
            "/nativecheckout/v2/isv/transactions?MerchantId={$connectedMerchantId}",
            $payload,
        );
    }
}
