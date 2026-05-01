<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\Exceptions\AuthenticationException;

/**
 * Low-level HTTP client for ISV operations.
 *
 * Supports 3 auth modes:
 * 1. Bearer token (ISV OAuth) for New API (/checkout/v2/isv/, /ecr/isv/v1/, /platforms/v1/)
 * 2. Basic Auth (ISV MerchantID:APIKey) for own-account Legacy API
 * 3. Composite Basic Auth (ResellerID:ConnMerchantID / ResellerAPIKey) for connected merchants
 */
final class HttpClient
{
    private ?string $accessToken = null;

    private ?float $tokenExpiresAt = null;

    private readonly Client $guzzle;

    public function __construct(
        private readonly IsvConfig $config,
        ?Client $guzzle = null,
    ) {
        $this->guzzle = $guzzle ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    // ==================== NEW API (Bearer) ====================

    /** @return array<string, mixed> */
    public function get(string $path, array $query = []): array
    {
        return $this->requestBearer('GET', $this->config->apiUrl().$path, [
            RequestOptions::QUERY => $query,
        ]);
    }

    /** @return array<string, mixed> */
    public function post(string $path, array $body = []): array
    {
        return $this->requestBearer('POST', $this->config->apiUrl().$path, [
            RequestOptions::JSON => $body,
        ]);
    }

    /** @return array<string, mixed> */
    public function put(string $path, array $body = []): array
    {
        return $this->requestBearer('PUT', $this->config->apiUrl().$path, [
            RequestOptions::JSON => $body,
        ]);
    }

    /** @return array<string, mixed> */
    public function delete(string $path, array $body = []): array
    {
        return $this->requestBearer('DELETE', $this->config->apiUrl().$path, [
            RequestOptions::JSON => $body,
        ]);
    }

    // ==================== LEGACY API — ISV Basic Auth ====================

    /** @return array<string, mixed> */
    public function legacyGet(string $path, array $query = []): array
    {
        return $this->requestBasic('GET', $this->config->legacyUrl().$path, [
            RequestOptions::QUERY => $query,
        ], $this->config->merchantId, $this->config->apiKey);
    }

    /** @return array<string, mixed> */
    public function legacyPost(string $path, array $body = []): array
    {
        return $this->requestBasic('POST', $this->config->legacyUrl().$path, [
            RequestOptions::JSON => $body,
        ], $this->config->merchantId, $this->config->apiKey);
    }

    /** @return array<string, mixed> */
    public function legacyDeleteUrl(string $fullUrl): array
    {
        return $this->requestBasic('DELETE', $fullUrl, [], $this->config->merchantId, $this->config->apiKey);
    }

    // ==================== LEGACY API — Composite Basic Auth (Connected Merchants) ====================

    /**
     * POST on Legacy API with Composite Basic Auth.
     *
     * Auth: {ResellerID}:{ConnectedMerchantID} / {ResellerAPIKey}
     * Used for: capture preauth, recurring charge, transaction operations on connected merchants.
     *
     * @return array<string, mixed>
     */
    public function compositePost(string $path, array $body, string $connectedMerchantId): array
    {
        return $this->requestBasic('POST', $this->config->legacyUrl().$path, [
            RequestOptions::JSON => $body,
        ], $this->config->compositeUsername($connectedMerchantId), $this->config->resellerApiKey);
    }

    /**
     * GET on Legacy API with Composite Basic Auth.
     *
     * @return array<string, mixed>
     */
    public function compositeGet(string $path, string $connectedMerchantId, array $query = []): array
    {
        return $this->requestBasic('GET', $this->config->legacyUrl().$path, [
            RequestOptions::QUERY => $query,
        ], $this->config->compositeUsername($connectedMerchantId), $this->config->resellerApiKey);
    }

    /**
     * DELETE on Legacy API with Composite Basic Auth.
     *
     * @return array<string, mixed>
     */
    public function compositeDeleteUrl(string $fullUrl, string $connectedMerchantId): array
    {
        return $this->requestBasic('DELETE', $fullUrl, [],
            $this->config->compositeUsername($connectedMerchantId), $this->config->resellerApiKey);
    }

    // ==================== AUTH ====================

    public function invalidateToken(): void
    {
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
    }

    // ==================== INTERNALS ====================

    /** @return array<string, mixed> */
    private function requestBearer(string $method, string $url, array $options = []): array
    {
        $this->authenticate();

        $options[RequestOptions::HEADERS] = array_merge(
            $options[RequestOptions::HEADERS] ?? [],
            ['Authorization' => 'Bearer '.$this->accessToken],
        );

        return $this->doRequest($method, $url, $options);
    }

    /** @return array<string, mixed> */
    private function requestBasic(string $method, string $url, array $options, string $username, string $password): array
    {
        $options[RequestOptions::AUTH] = [$username, $password];

        return $this->doRequest($method, $url, $options);
    }

    /** @return array<string, mixed> */
    private function doRequest(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->guzzle->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new ApiException("HTTP request failed: {$e->getMessage()}", 0, null, $e);
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        // Some endpoints return empty body on success (e.g. ECR sale 200)
        if ($body === '' && $statusCode >= 200 && $statusCode < 300) {
            return [];
        }

        $decoded = json_decode($body, true) ?? [];

        if ($statusCode >= 400) {
            $message = $decoded['ErrorText']
                ?? $decoded['message']
                ?? $decoded['detail']
                ?? $decoded['Message']
                ?? "HTTP {$statusCode}";

            throw new ApiException($message, $statusCode, $decoded);
        }

        return $decoded;
    }

    private function authenticate(): void
    {
        if ($this->accessToken && $this->tokenExpiresAt && microtime(true) < $this->tokenExpiresAt) {
            return;
        }

        try {
            $response = $this->guzzle->post(
                $this->config->accountsUrl().'/connect/token',
                [
                    RequestOptions::AUTH => [$this->config->clientId, $this->config->clientSecret],
                    RequestOptions::FORM_PARAMS => ['grant_type' => 'client_credentials'],
                    RequestOptions::HEADERS => ['Content-Type' => 'application/x-www-form-urlencoded'],
                ],
            );
        } catch (GuzzleException $e) {
            throw new AuthenticationException('ISV token request failed: '.$e->getMessage(), $e);
        }

        $statusCode = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($statusCode !== 200 || empty($data['access_token'])) {
            throw new AuthenticationException(
                'ISV OAuth2 authentication failed: '.($data['error'] ?? $data['error_description'] ?? "HTTP {$statusCode}"),
            );
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = microtime(true) + ($data['expires_in'] ?? 3600) - 60;
    }
}
