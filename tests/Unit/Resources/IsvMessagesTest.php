<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\Exceptions\ApiException;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\IsvMessages;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class IsvMessagesTest extends TestCase
{
    private IsvConfig $demoConfig;

    private IsvConfig $productionConfig;

    protected function setUp(): void
    {
        $this->demoConfig = new IsvConfig(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            merchantId: 'isv-merchant-uuid',
            apiKey: 'api-key',
            resellerId: 'reseller-uuid',
            resellerApiKey: 'reseller-api-key',
            environment: Environment::DEMO,
        );

        $this->productionConfig = new IsvConfig(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            merchantId: 'isv-merchant-uuid',
            apiKey: 'api-key',
            resellerId: 'reseller-uuid',
            resellerApiKey: 'reseller-api-key',
            environment: Environment::PRODUCTION,
        );
    }

    #[Test]
    public function it_registers_webhook_via_composite_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->demoConfig);
        $resource = new IsvMessages($http, $this->demoConfig);

        $callbackUrl = 'https://app.example.com/api/webhooks/viva';
        $expectedResponse = [
            'MessageId' => 'msg-uuid-123',
            'EventTypeId' => 768,
            'Url' => $callbackUrl,
            'IsActive' => true,
        ];

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, $expectedResponse));

        $result = $resource->register('merchant-abc', 768, $callbackUrl);

        $this->assertSame('msg-uuid-123', $result['MessageId']);
        $this->assertSame(768, $result['EventTypeId']);
        $this->assertSame($callbackUrl, $result['Url']);

        // Verify the HTTP request was sent to the correct endpoint
        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertStringContainsString('/api/messages/config', (string) $lastRequest->getUri());
        $this->assertSame('POST', $lastRequest->getMethod());

        // Verify PascalCase body (Legacy API convention)
        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame($callbackUrl, $body['Url']);
        $this->assertSame(768, $body['EventTypeId']);
        $this->assertSame(0, $body['MessageTypeId']);
        $this->assertTrue($body['IsActive']);
        $this->assertArrayNotHasKey('url', $body);
        $this->assertArrayNotHasKey('eventTypeId', $body);
    }

    #[Test]
    public function it_lists_webhooks_via_composite_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->demoConfig);
        $resource = new IsvMessages($http, $this->demoConfig);

        $expectedList = [
            ['MessageId' => 'msg-1', 'EventTypeId' => 768, 'Url' => 'https://example.com/hook', 'IsActive' => true],
            ['MessageId' => 'msg-2', 'EventTypeId' => 769, 'Url' => 'https://example.com/hook', 'IsActive' => true],
        ];

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, $expectedList));

        $result = $resource->list('merchant-abc');

        $this->assertCount(2, $result);
        $this->assertSame('msg-1', $result[0]['MessageId']);
        $this->assertSame(769, $result[1]['EventTypeId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertStringContainsString('/api/messages/config', (string) $lastRequest->getUri());
    }

    #[Test]
    public function it_deletes_webhook_via_composite_delete(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->demoConfig);
        $resource = new IsvMessages($http, $this->demoConfig);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, []));

        $result = $resource->delete('merchant-abc', 'msg-uuid-456');

        $this->assertSame([], $result);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('DELETE', $lastRequest->getMethod());
        // Demo environment: legacy URL = https://demo.vivapayments.com
        $this->assertStringContainsString('demo.vivapayments.com', (string) $lastRequest->getUri());
        $this->assertStringContainsString('/api/messages/config/msg-uuid-456', (string) $lastRequest->getUri());
    }

    #[Test]
    public function it_uses_production_legacy_url_for_delete_in_production(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->productionConfig);
        $resource = new IsvMessages($http, $this->productionConfig);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, []));

        $resource->delete('merchant-prod', 'msg-prod-789');

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertStringContainsString('www.vivapayments.com', (string) $lastRequest->getUri());
        $this->assertStringContainsString('/api/messages/config/msg-prod-789', (string) $lastRequest->getUri());
    }

    #[Test]
    public function it_throws_api_exception_on_400_error(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->demoConfig);
        $resource = new IsvMessages($http, $this->demoConfig);

        $mockHandler->append(
            GuzzleMockFactory::errorResponse(400, 'duplicate subscription', 3732),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('duplicate subscription');

        $resource->register('merchant-abc', 768, 'https://example.com/hook');
    }
}
