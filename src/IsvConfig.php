<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv;

use QrCommunication\VivaIsv\Enums\Environment;

/**
 * ISV configuration — holds all 3 credential sets.
 *
 * 1. ISV OAuth (Client ID + Secret) → Bearer token for New API
 * 2. ISV Merchant (Merchant ID + API Key) → Basic Auth for Legacy API (own account)
 * 3. Reseller (Reseller ID + API Key) → Composite Basic Auth for connected merchants
 */
final class IsvConfig
{
    public readonly Environment $environment;

    public function __construct(
        // ISV OAuth credentials (Bearer token)
        public readonly string $clientId,
        public readonly string $clientSecret,
        // ISV Merchant credentials (Basic Auth for own account)
        public readonly string $merchantId,
        public readonly string $apiKey,
        // Reseller credentials (Composite Basic Auth for connected merchants)
        public readonly string $resellerId,
        public readonly string $resellerApiKey,
        // Environment
        string|Environment $environment = Environment::DEMO,
    ) {
        $this->environment = $environment instanceof Environment
            ? $environment
            : Environment::from($environment);
    }

    public function accountsUrl(): string
    {
        return $this->environment->accountsUrl();
    }

    public function apiUrl(): string
    {
        return $this->environment->apiUrl();
    }

    public function legacyUrl(): string
    {
        return $this->environment->legacyUrl();
    }

    public function checkoutUrl(): string
    {
        return $this->environment->checkoutUrl();
    }

    /**
     * Build the composite Basic Auth username for a connected merchant.
     *
     * Format: {ResellerID}:{ConnectedMerchantID}
     * This format is NOT documented by Viva — discovered empirically.
     */
    public function compositeUsername(string $connectedMerchantId): string
    {
        return "{$this->resellerId}:{$connectedMerchantId}";
    }
}
