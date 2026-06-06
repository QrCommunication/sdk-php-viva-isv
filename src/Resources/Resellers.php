<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Resources;

use QrCommunication\VivaIsv\HttpClient;

/**
 * Reseller transactions — cash & bill payments, OTP validation, reseller orders.
 *
 * Uses /resellers/v1/ endpoints on the New API with Bearer ISV token (camelCase).
 * These cover the cash/bill payment flow (validate → send OTP → charge) and
 * reseller-scoped order creation.
 *
 * Each method forwards its payload as-is so callers stay aligned with the Viva
 * request schema without the SDK guessing at evolving field sets.
 *
 * @see https://developer.viva.com/apis-for-payments/payment-api/
 */
final class Resellers
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Validate a cash payment before charging.
     *
     * POST /resellers/v1/transactions/cashPayments:validate
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateCashPayment(array $payload): array
    {
        return $this->http->post('/resellers/v1/transactions/cashPayments:validate', $payload);
    }

    /**
     * Validate a bill payment before charging.
     *
     * POST /resellers/v1/transactions/billPayments:validate
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateBillPayment(array $payload): array
    {
        return $this->http->post('/resellers/v1/transactions/billPayments:validate', $payload);
    }

    /**
     * Send a one-time password for a cash payment.
     *
     * POST /resellers/v1/transactions/cashPayments:sendotp
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendCashPaymentOtp(array $payload): array
    {
        return $this->http->post('/resellers/v1/transactions/cashPayments:sendotp', $payload);
    }

    /**
     * Send a one-time password for a bill payment.
     *
     * POST /resellers/v1/transactions/billPayments:sendotp
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendBillPaymentOtp(array $payload): array
    {
        return $this->http->post('/resellers/v1/transactions/billPayments:sendotp', $payload);
    }

    /**
     * Charge a cash payment.
     *
     * POST /resellers/v1/transactions/cashPayments
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cashPayment(array $payload): array
    {
        return $this->http->post('/resellers/v1/transactions/cashPayments', $payload);
    }

    /**
     * Charge a bill payment.
     *
     * POST /resellers/v1/transactions/billPayments
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function billPayment(array $payload): array
    {
        return $this->http->post('/resellers/v1/transactions/billPayments', $payload);
    }

    /**
     * Create a reseller-scoped payment order.
     *
     * POST /resellers/v1/orders
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->http->post('/resellers/v1/orders', $payload);
    }
}
