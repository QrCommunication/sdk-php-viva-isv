<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Account management via /isv/v1/accounts.
 *
 * Different from ConnectedAccounts (/platforms/v1/accounts):
 * - ISV-specific onboarding flow with branding options
 * - Uses the ISV namespace /isv/v1/ instead of /platforms/v1/
 * - Bearer ISV OAuth token
 */
final class IsvAccounts
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Create an ISV connected account.
     *
     * @param  string  $email  Merchant email address
     * @param  string  $returnUrl  Redirect URL after onboarding
     * @param  string|null  $partnerName  ISV platform name (shown in onboarding)
     * @param  string|null  $primaryColor  Hex color for onboarding branding
     * @param  string|null  $logoUrl  ISV platform logo URL
     * @return array{accountId: string, invitation: array{redirectUrl: string}}
     */
    public function create(
        string $email,
        string $returnUrl,
        ?string $partnerName = null,
        ?string $primaryColor = null,
        ?string $logoUrl = null,
    ): array {
        $payload = [
            'email' => $email,
            'returnUrl' => $returnUrl,
        ];

        $branding = array_filter([
            'partnerName' => $partnerName,
            'primaryColor' => $primaryColor,
            'logoUrl' => $logoUrl,
        ]);

        if (! empty($branding)) {
            $payload['branding'] = $branding;
        }

        return $this->http->post('/isv/v1/accounts', $payload);
    }

    /**
     * Get an ISV connected account by ID.
     *
     * @return array<string, mixed>  Account data with verificationStatus, merchantId, etc.
     */
    public function get(string $accountId): array
    {
        return $this->http->get("/isv/v1/accounts/{$accountId}");
    }

    /**
     * List all ISV connected accounts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->http->get('/isv/v1/accounts');
    }
}
