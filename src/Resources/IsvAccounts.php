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
 *
 * **Use this resource over ConnectedAccounts on pure-ISV partners** —
 * `/platforms/v1/*` (Marketplace API) returns HTTP 403 unless the Marketplace
 * API is explicitly enabled on the merchant. `/isv/v1/*` is the canonical
 * endpoint for ISV partners and works in production by default.
 *
 * @see prod-findings.md  Documented behavior on production ISV accounts.
 */
final class IsvAccounts
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Create an ISV connected account.
     *
     * Note: Viva does NOT deduplicate by email — calling this multiple times
     * with the same email creates multiple distinct `accountId`s. Track the
     * active accountId application-side to avoid orphan invitations.
     *
     * @param  string  $email  Merchant email address. If already linked to a
     *                         business account on Viva, the user will see a
     *                         "select existing account" screen during onboarding.
     *                         Use a fresh/unused email (or `+tag` alias) to
     *                         force a clean KYB flow.
     * @param  string  $returnUrl  URL to redirect to after the user finishes the
     *                             KYB form. Should land on a page that calls
     *                             `complete-onboarding` to sync verification.
     * @param  string|null  $partnerName  ISV platform name (shown in onboarding)
     * @param  string|null  $primaryColor  Hex color for onboarding branding
     * @param  string|null  $logoUrl  ISV platform logo URL
     * @return array{accountId: string, invitation: array{redirectUrl: string, email: string, created: string}}
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
     * Response shape (key fields):
     *   - `accountId`     : same as $accountId
     *   - `merchantId`    : ?string  (null until KYB submitted)
     *   - `verified`      : bool     (true once Viva validates KYB)
     *   - `acquiringEnabled` : bool  (true → can already accept payments)
     *   - `legalName`, `email`, `vatNumber`, `taxNumber`, `registrationNumber`
     *   - `invitation`    : ['email', 'redirectUrl', 'created']  (always present)
     *
     * @return array<string, mixed>
     */
    public function get(string $accountId): array
    {
        return $this->http->get("/isv/v1/accounts/{$accountId}");
    }

    /**
     * Get the KYB onboarding URL from an existing account's invitation.
     *
     * Use this when `viva_account_id` already exists application-side and the
     * merchant needs to (re)complete KYB. The invitation URL stays valid until
     * verified — calling create() again would just create an orphan accountId.
     *
     * @return string|null  invitation.redirectUrl, or null if account is fully
     *                      verified (no invitation exposed by Viva at that point)
     */
    public function getOnboardingUrl(string $accountId): ?string
    {
        $info = $this->get($accountId);

        return $info['invitation']['redirectUrl'] ?? null;
    }

    /**
     * Check whether the connected account has completed KYB verification.
     *
     * Note: a merchant can already receive payments while verified=false if
     * `acquiringEnabled=true` (KYB submitted but Viva still reviewing). Use
     * {@see isAcquiringEnabled()} for the looser "can accept payments" check.
     */
    public function isVerified(string $accountId): bool
    {
        $info = $this->get($accountId);

        return ($info['verified'] ?? false) === true;
    }

    /**
     * Check whether the connected account can already accept payments.
     *
     * Often becomes `true` before `verified` does — Viva enables acquiring
     * once KYB is submitted, while verification adds higher transaction limits.
     */
    public function isAcquiringEnabled(string $accountId): bool
    {
        $info = $this->get($accountId);

        return ($info['acquiringEnabled'] ?? false) === true;
    }

    /**
     * List all ISV connected accounts.
     *
     * ⚠️ **Returns HTTP 405 in production** — Viva does not expose a listing
     * endpoint for ISV accounts. Track created accountIds application-side.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \QrCommunication\VivaIsv\Exceptions\ApiException Always (HTTP 405)
     */
    public function list(): array
    {
        return $this->http->get('/isv/v1/accounts');
    }
}
