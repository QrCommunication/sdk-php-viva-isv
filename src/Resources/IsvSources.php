<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * ISV Payment Sources — register new payment sources on the ISV merchant account.
 *
 * Uses POST /api/sources on the Legacy API with ISV Basic Auth (own account).
 * Sources let merchants group their sales into meaningful buckets (e-commerce,
 * card-present, etc.) manageable through the Viva banking app.
 *
 * NOTE: unlike most Legacy endpoints, /api/sources expects a camelCase body
 * (e.g. `sourceCode`, `walletId`), so the payload is passed through as-is.
 *
 * @see https://developer.viva.com/apis-for-payments/payment-api/
 */
final class IsvSources
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Add a new payment source to the ISV merchant account.
     *
     * The payload is forwarded unchanged. Required fields per Viva: `name` and
     * `sourceCode`. For e-commerce sources, `domain`/`isSecure`/`pathSuccess`/
     * `pathFail` are also required; for card-present, `phone`/`address`/`walletId`.
     *
     * @param  array<string, mixed>  $payload  Source attributes (camelCase)
     * @return array<string, mixed>  Created source data
     */
    public function create(array $payload): array
    {
        return $this->http->legacyPost('/api/sources', $payload);
    }
}
