<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * Connected Merchant Account management.
 *
 * Uses POST /platforms/v1/accounts (ISV Bearer token) to create
 * and manage sub-merchant accounts under the ISV platform.
 */
final class ConnectedAccounts
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Create a connected merchant account.
     *
     * @param  string  $email  Merchant email
     * @param  string  $returnUrl  URL to redirect after KYB onboarding
     * @param  string|null  $partnerName  ISV platform name (shown in Viva dashboard)
     * @param  string|null  $logoUrl  ISV platform logo
     * @return array{accountId: string, invitation: array{redirectUrl: string}}
     */
    public function create(
        string $email,
        string $returnUrl,
        ?string $partnerName = null,
        ?string $logoUrl = null,
    ): array {
        $payload = [
            'email' => $email,
            'returnUrl' => $returnUrl,
        ];

        if ($partnerName || $logoUrl) {
            $payload['branding'] = array_filter([
                'partnerName' => $partnerName,
                'logoUrl' => $logoUrl,
            ]);
        }

        return $this->http->post('/platforms/v1/accounts', $payload);
    }

    /**
     * Get connected account details (including verification status).
     *
     * @return array<string, mixed>  Account data with verificationStatus, merchantId, etc.
     */
    public function get(string $accountId): array
    {
        return $this->http->get("/platforms/v1/accounts/{$accountId}");
    }

    /**
     * Get the KYB onboarding URL from the account invitation.
     *
     * @return string|null  Onboarding redirect URL, or null if already verified
     */
    public function onboardingUrl(string $accountId): ?string
    {
        $account = $this->get($accountId);

        return $account['invitation']['redirectUrl'] ?? null;
    }

    /**
     * Check if a connected account is fully verified.
     */
    public function isVerified(string $accountId): bool
    {
        $account = $this->get($accountId);
        $status = $account['verificationStatus'] ?? '';

        return in_array($status, ['Verified', 'Active'], true);
    }

    /**
     * List all connected accounts (paginated).
     *
     * @return array<string, mixed>  Paginated list of connected accounts
     */
    public function list(): array
    {
        return $this->http->get('/platforms/v1/accounts');
    }

    /**
     * Update connected account attributes.
     *
     * @param  string  $accountId  Connected account UUID
     * @param  array<string, mixed>  $attributes  Fields to update
     * @return array<string, mixed>
     */
    public function update(string $accountId, array $attributes): array
    {
        return $this->http->post("/platforms/v1/accounts/{$accountId}", $attributes);
    }

    /**
     * Delete/disconnect a connected account.
     *
     * @param  string  $accountId  Connected account UUID
     * @return array<string, mixed>
     */
    public function delete(string $accountId): array
    {
        return $this->http->delete("/platforms/v1/accounts/{$accountId}");
    }
}
