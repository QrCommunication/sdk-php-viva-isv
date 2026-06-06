<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\IsvSources;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class IsvSourcesTest extends TestCase
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
    public function it_creates_a_source_via_legacy_basic_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'sourceCode' => '1234',
            'name' => 'API test',
        ]));

        $payload = [
            'name' => 'API test',
            'sourceCode' => '1234',
            'domain' => 'www.example.com',
            'isSecure' => true,
        ];

        $result = $resource->create($payload);

        $this->assertSame('1234', $result['sourceCode']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('demo.vivapayments.com', (string) $lastRequest->getUri());
        $this->assertStringContainsString('/api/sources', (string) $lastRequest->getUri());

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame('1234', $body['sourceCode']);
        $this->assertSame('API test', $body['name']);
        $this->assertTrue($body['isSecure']);
    }
}
