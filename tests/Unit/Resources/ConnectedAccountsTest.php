<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\ConnectedAccounts;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class ConnectedAccountsTest extends TestCase
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
    public function it_creates_a_connected_account_via_bearer_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new ConnectedAccounts($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'accountId' => 'acc-uuid',
            'invitation' => ['redirectUrl' => 'https://kyb.url'],
        ]));

        $result = $resource->create('merchant@example.com', 'https://return.url', 'My Platform');

        $this->assertSame('acc-uuid', $result['accountId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('/platforms/v1/accounts', (string) $lastRequest->getUri());
    }

    #[Test]
    public function it_updates_a_connected_account_via_bearer_patch(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new ConnectedAccounts($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['updated' => true]));

        $result = $resource->update('acc-uuid', [
            'payouts' => ['statementDescriptor' => 'Weekly payouts', 'interval' => 2],
        ]);

        $this->assertTrue($result['updated']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('PATCH', $lastRequest->getMethod());
        $this->assertStringContainsString('/platforms/v1/accounts/acc-uuid', (string) $lastRequest->getUri());

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame('Weekly payouts', $body['payouts']['statementDescriptor']);
    }
}
