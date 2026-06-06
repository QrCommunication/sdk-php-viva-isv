<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Payment Sources — create payment sources, with idempotent helpers.
 *
 * Viva documents ONLY `POST /api/sources` (create). There is NO documented
 * endpoint to list/retrieve sources via the API — they are otherwise managed
 * through the Self Care / banking web app. Idempotency therefore relies on the
 * documented `409 Conflict — Source already exists with this source code`
 * response, NOT on a list-then-create lookup.
 *
 * Two scopes:
 *  - Own ISV account     → Legacy Basic Auth      (create / ensure)
 *  - Connected merchant  → Composite Basic Auth   (*ForMerchant variants)
 *
 * Body is camelCase (per Viva spec): required `name` + `sourceCode`; e-commerce
 * also requires `domain` / `isSecure` / `pathSuccess` / `pathFail`; card-present
 * requires `phone` / `address` / `walletId` / `isPhysical` / `latitude` / `longitude`.
 *
 * @see https://developer.viva.com/apis-for-payments/payment-api/#tag/Sources
 */
final class IsvSources
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    // ==================== CREATE ====================

    /**
     * Create a source on the ISV's own account (Legacy Basic Auth).
     *
     * Throws ApiException with httpStatus 409 if a source with the same
     * `sourceCode` already exists (use ensure() to treat that as success).
     *
     * @param  array<string, mixed>  $payload  Source attributes (camelCase). Required: name, sourceCode.
     * @return array<string, mixed>  Created source data
     */
    public function create(array $payload): array
    {
        return $this->http->legacyPost('/api/sources', $payload);
    }

    /**
     * Create a source on behalf of a connected merchant (Composite Basic Auth).
     *
     * @param  string  $connectedMerchantId  The connected merchant UUID
     * @param  array<string, mixed>  $payload  Source attributes (camelCase). Required: name, sourceCode.
     * @return array<string, mixed>  Created source data
     */
    public function createForMerchant(string $connectedMerchantId, array $payload): array
    {
        return $this->http->compositePost('/api/sources', $payload, $connectedMerchantId);
    }

    // ==================== ENSURE (idempotent create-if-absent) ====================

    /**
     * Create the source unless it already exists (ISV own account).
     *
     * Uses Viva's documented `409 Source already exists` response: attempts the
     * create and, on 409, returns a soft marker instead of throwing — so calling
     * this repeatedly never produces a duplicate nor an error.
     *
     * @param  array<string, mixed>  $payload  Source attributes (camelCase). Required: name, sourceCode.
     * @return array<string, mixed>  Created source data, or
     *                               ['sourceCode' => string, 'status' => 'already_exists'] on 409
     */
    public function ensure(array $payload): array
    {
        try {
            return $this->create($payload);
        } catch (ApiException $e) {
            return $this->handleConflict($e, $payload);
        }
    }

    /**
     * Create the connected merchant's source unless it already exists (Composite Auth).
     *
     * @param  string  $connectedMerchantId  The connected merchant UUID
     * @param  array<string, mixed>  $payload  Source attributes (camelCase). Required: name, sourceCode.
     * @return array<string, mixed>  Created source data, or
     *                               ['sourceCode' => string, 'status' => 'already_exists'] on 409
     */
    public function ensureForMerchant(string $connectedMerchantId, array $payload): array
    {
        try {
            return $this->createForMerchant($connectedMerchantId, $payload);
        } catch (ApiException $e) {
            return $this->handleConflict($e, $payload);
        }
    }

    // ==================== INTERNALS ====================

    /**
     * Swallow the documented 409 (source already exists), rethrow anything else.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleConflict(ApiException $e, array $payload): array
    {
        if ($e->httpStatus === 409) {
            return [
                'sourceCode' => $payload['sourceCode'] ?? null,
                'status' => 'already_exists',
            ];
        }

        throw $e;
    }
}
