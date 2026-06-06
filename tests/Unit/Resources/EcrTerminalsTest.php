<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\EcrTerminals;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class EcrTerminalsTest extends TestCase
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
    public function it_refunds_a_sale_via_bearer_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new EcrTerminals($http, $this->config);

        // 1) OAuth token, 2) refund response
        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, []));

        $result = $resource->refund(
            sessionId: 'parent-session-uuid',
            amount: 1170,
            merchantReference: 'ref-42',
            terminalId: 16014231,
            terminalMerchantId: 'terminal-merchant-uuid',
            cashRegisterId: 'CR-01',
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('session_id', $result);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('/ecr/isv/v1/transactions:refund', (string) $lastRequest->getUri());

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame('parent-session-uuid', $body['parentSessionId']);
        $this->assertSame(1170, $body['amount']);
        $this->assertSame('978', $body['currencyCode']);
        $this->assertSame('ref-42', $body['merchantReference']);
        $this->assertSame(16014231, $body['terminalId']);
        $this->assertSame('terminal-merchant-uuid', $body['isvDetails']['terminalMerchantId']);
        $this->assertNotSame($body['parentSessionId'], $body['sessionId']);
    }

    #[Test]
    public function it_creates_an_action_via_bearer_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new EcrTerminals($http, $this->config);

        $payload = [
            'terminalId' => '16000010',
            'cashRegisterId' => 'XDE384678UY',
            'actionType' => 'aade-fim-control',
            'isvDetails' => ['terminalMerchantId' => 'terminal-merchant-uuid'],
            'request' => ['token' => 'ECR0210U/RCFB77000041'],
        ];
        $expected = $payload + ['actionId' => 'action-uuid-123'];

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, $expected));

        $result = $resource->createAction($payload);

        $this->assertSame('action-uuid-123', $result['actionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('/ecr/isv/v1/actions', (string) $lastRequest->getUri());

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame('aade-fim-control', $body['actionType']);
        $this->assertSame('terminal-merchant-uuid', $body['isvDetails']['terminalMerchantId']);
    }

    #[Test]
    public function it_gets_an_action_via_bearer_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new EcrTerminals($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'actionId' => 'action-uuid-123',
            'successfullyProcessed' => true,
        ]));

        $result = $resource->getAction('action-uuid-123');

        $this->assertTrue($result['successfullyProcessed']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertStringContainsString('/ecr/isv/v1/actions/action-uuid-123', (string) $lastRequest->getUri());
    }
}
