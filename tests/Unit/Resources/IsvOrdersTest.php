<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\IsvOrders;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class IsvOrdersTest extends TestCase
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

    #[Test]
    public function it_retrieves_an_order_via_composite_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvOrders($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'OrderCode' => 3958121726095215,
            'StateId' => 3,
            'RequestAmount' => 100,
        ]));

        $result = $resource->retrieve(3958121726095215, 'merchant-abc');

        $this->assertSame(3, $result['StateId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertStringContainsString('/api/orders/3958121726095215', (string) $lastRequest->getUri());
        // Composite Basic Auth header present
        $this->assertNotEmpty($lastRequest->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_cancels_an_order_via_composite_delete(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvOrders($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'OrderCode' => 7568599983572609,
            'Success' => true,
            'ErrorCode' => 0,
        ]));

        $result = $resource->cancel(7568599983572609, 'merchant-abc');

        $this->assertTrue($result['Success']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('DELETE', $lastRequest->getMethod());
        $this->assertStringContainsString('demo.vivapayments.com', (string) $lastRequest->getUri());
        $this->assertStringContainsString('/api/orders/7568599983572609', (string) $lastRequest->getUri());
    }
}
