<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Payment Sources — create / list / dedup payment sources.
 *
 * Two scopes:
 *  - Own ISV account     → Legacy Basic Auth   (create/list/find/ensure)
 *  - Connected merchant  → Composite Basic Auth (*ForMerchant variants)
 *
 * Sources define how a merchant's sales are grouped and, for e-commerce, the
 * redirect URLs used by Smart Checkout. The `/api/sources` body is camelCase
 * (per Viva spec): required `name` + `sourceCode`; e-commerce also requires
 * `domain` / `isSecure` / `pathSuccess` / `pathFail`; card-present requires
 * `phone` / `address` / `walletId` / `isPhysical` / `latitude` / `longitude`.
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

    // ==================== LIST ====================

    /**
     * List the ISV's own payment sources.
     *
     * @return array<int, array<string, mixed>>  Flat list of sources
     */
    public function list(): array
    {
        return $this->normalize($this->http->legacyGet('/api/sources'));
    }

    /**
     * List a connected merchant's payment sources (Composite Basic Auth).
     *
     * @param  string  $connectedMerchantId  The connected merchant UUID
     * @return array<int, array<string, mixed>>  Flat list of sources
     */
    public function listForMerchant(string $connectedMerchantId): array
    {
        return $this->normalize($this->http->compositeGet('/api/sources', $connectedMerchantId));
    }

    // ==================== FIND (dedup) ====================

    /**
     * Find one of the ISV's own sources by its sourceCode.
     *
     * @return array<string, mixed>|null  The matching source, or null if none
     */
    public function find(string $sourceCode): ?array
    {
        return $this->pick($this->list(), $sourceCode);
    }

    /**
     * Find a connected merchant's source by its sourceCode (Composite Basic Auth).
     *
     * @return array<string, mixed>|null  The matching source, or null if none
     */
    public function findForMerchant(string $connectedMerchantId, string $sourceCode): ?array
    {
        return $this->pick($this->listForMerchant($connectedMerchantId), $sourceCode);
    }

    // ==================== ENSURE (idempotent create-if-absent) ====================

    /**
     * Return the ISV source matching $payload['sourceCode'], creating it only if absent.
     *
     * Avoids duplicate sources: lists first, returns the existing one when found,
     * otherwise creates it.
     *
     * @param  array<string, mixed>  $payload  Source attributes (camelCase). Required: name, sourceCode.
     * @return array<string, mixed>  The existing or newly created source
     */
    public function ensure(array $payload): array
    {
        $sourceCode = (string) ($payload['sourceCode'] ?? '');
        $existing = $sourceCode !== '' ? $this->find($sourceCode) : null;

        return $existing ?? $this->create($payload);
    }

    /**
     * Return a connected merchant's source matching $payload['sourceCode'],
     * creating it only if absent (Composite Basic Auth).
     *
     * @param  string  $connectedMerchantId  The connected merchant UUID
     * @param  array<string, mixed>  $payload  Source attributes (camelCase). Required: name, sourceCode.
     * @return array<string, mixed>  The existing or newly created source
     */
    public function ensureForMerchant(string $connectedMerchantId, array $payload): array
    {
        $sourceCode = (string) ($payload['sourceCode'] ?? '');
        $existing = $sourceCode !== ''
            ? $this->findForMerchant($connectedMerchantId, $sourceCode)
            : null;

        return $existing ?? $this->createForMerchant($connectedMerchantId, $payload);
    }

    // ==================== INTERNALS ====================

    /**
     * Normalize a /api/sources list response to a flat array of sources.
     *
     * Viva may wrap the list under `Sources`/`sources`, return a bare list, or a
     * single object — this flattens all shapes.
     *
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    private function normalize(array $response): array
    {
        foreach (['Sources', 'sources', 'data', 'Data'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }

        if ($response === []) {
            return [];
        }

        return array_is_list($response) ? $response : [$response];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<string, mixed>|null
     */
    private function pick(array $sources, string $sourceCode): ?array
    {
        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            $code = (string) ($source['SourceCode'] ?? $source['sourceCode'] ?? '');
            if ($code === $sourceCode) {
                return $source;
            }
        }

        return null;
    }
}
