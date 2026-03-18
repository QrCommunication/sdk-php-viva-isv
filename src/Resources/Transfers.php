<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * Marketplace Transfers — send funds to connected accounts, reverse transfers.
 *
 * Uses Bearer ISV OAuth token on /platforms/v1/transfers.
 *
 * @see https://developer.viva.com/apis-for-payments/marketplace-api/
 */
final class Transfers
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Send funds to a connected (seller) account.
     *
     * Can be linked to an existing transactionId or done standalone.
     *
     * @param  string  $targetAccountId  Connected account UUID
     * @param  int  $amount  Amount in cents
     * @param  string|null  $sourceWalletId  Source wallet UUID (platform wallet)
     * @param  string|null  $transactionId  Link to an existing transaction
     * @param  string|null  $description  Transfer description
     * @return array{transferId: string}
     */
    public function send(string $targetAccountId, int $amount, ?string $sourceWalletId = null, ?string $transactionId = null, ?string $description = null): array
    {
        return $this->http->post('/platforms/v1/transfers', array_filter([
            'targetAccountId' => $targetAccountId,
            'amount' => $amount,
            'sourceWalletId' => $sourceWalletId,
            'transactionId' => $transactionId,
            'description' => $description,
        ]));
    }

    /**
     * Reverse a transfer made to a connected account.
     *
     * @param  string  $transferId  The transfer UUID to reverse
     * @param  int|null  $amount  Amount in cents (null = full reversal)
     * @return array{transferId: string}
     */
    public function reverse(string $transferId, ?int $amount = null): array
    {
        return $this->http->post("/platforms/v1/transfers/{$transferId}:reverse", array_filter([
            'amount' => $amount,
        ]));
    }
}
