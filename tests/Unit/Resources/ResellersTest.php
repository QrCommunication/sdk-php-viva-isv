<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\Resellers;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class ResellersTest extends TestCase
{
    private IsvConfig $config;

    protected function setUp(): void
    {
        $this->config = new IsvConfig(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            merchantId: 'isv-merchant-uuid',
            apiKey: 'api-key',
            resellerId: 'reseller-uuid',
            resellerApiKey: 'reseller-api-key',
            environment: Environment::DEMO,
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function methodEndpointProvider(): array
    {
        return [
            'validateCashPayment' => ['validateCashPayment', '/resellers/v1/transactions/cashPayments:validate'],
            'validateBillPayment' => ['validateBillPayment', '/resellers/v1/transactions/billPayments:validate'],
            'sendCashPaymentOtp' => ['sendCashPaymentOtp', '/resellers/v1/transactions/cashPayments:sendotp'],
            'sendBillPaymentOtp' => ['sendBillPaymentOtp', '/resellers/v1/transactions/billPayments:sendotp'],
            'cashPayment' => ['cashPayment', '/resellers/v1/transactions/cashPayments'],
            'billPayment' => ['billPayment', '/resellers/v1/transactions/billPayments'],
            'createOrder' => ['createOrder', '/resellers/v1/orders'],
        ];
    }

    #[Test]
    #[DataProvider('methodEndpointProvider')]
    public function it_posts_to_the_correct_endpoint_via_bearer(string $method, string $endpoint): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new Resellers($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['ok' => true]));

        $payload = ['amount' => 1500, 'phone' => '306900000000'];
        $result = $resource->{$method}($payload);

        $this->assertTrue($result['ok']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('POST', $lastRequest->getMethod());

        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('demo-api.vivapayments.com', $uri);
        $this->assertStringContainsString($endpoint, $uri);

        // Bearer token attached
        $this->assertStringStartsWith('Bearer ', $lastRequest->getHeaderLine('Authorization'));

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame(1500, $body['amount']);
    }
}
