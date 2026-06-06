<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\Exceptions\ApiException;
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

    private function basicAuthUser(Request $request): string
    {
        $header = $request->getHeaderLine('Authorization');

        return base64_decode(substr($header, strlen('Basic ')), true) ?: '';
    }

    #[Test]
    public function it_creates_a_source_via_legacy_basic_post(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, ['sourceCode' => '1234', 'name' => 'API test']));

        $result = $resource->create([
            'name' => 'API test',
            'sourceCode' => '1234',
            'domain' => 'www.example.com',
            'isSecure' => true,
            'pathSuccess' => 'https://www.example.com/success',
            'pathFail' => 'https://www.example.com/fail',
        ]);

        $this->assertSame('1234', $result['sourceCode']);

        $req = $mock->getLastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringContainsString('/api/sources', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('https://www.example.com/success', $body['pathSuccess']);
        // Own account → ISV Basic Auth (merchantId:apiKey)
        $this->assertSame('isv-merchant-uuid:api-key', $this->basicAuthUser($req));
    }

    #[Test]
    public function it_creates_a_source_for_a_connected_merchant_via_composite_auth(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, ['sourceCode' => '4321']));

        $result = $resource->createForMerchant('merchant-x', [
            'name' => 'Boutique marchand',
            'sourceCode' => '4321',
            'domain' => 'boutique.fr',
            'isSecure' => true,
            'pathSuccess' => 'https://boutique.fr/ok',
            'pathFail' => 'https://boutique.fr/ko',
        ]);

        $this->assertSame('4321', $result['sourceCode']);

        $req = $mock->getLastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringContainsString('/api/sources', (string) $req->getUri());
        // Composite Basic Auth: {resellerId}:{connectedMerchantId} / {resellerApiKey}
        $this->assertSame('reseller-uuid:merchant-x:reseller-api-key', $this->basicAuthUser($req));
    }

    #[Test]
    public function ensure_returns_created_source_on_success(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, ['sourceCode' => '1234', 'name' => 'Created']));

        $result = $resource->ensure(['name' => 'Created', 'sourceCode' => '1234']);

        $this->assertSame('Created', $result['name']);
        $this->assertSame('POST', $mock->getLastRequest()->getMethod());
    }

    #[Test]
    public function ensure_for_merchant_treats_409_as_already_existing(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        // Viva's documented 409: "Source already exists with this source code"
        $mock->append(GuzzleMockFactory::errorResponse(409, 'Source already exists with this source code'));

        $result = $resource->ensureForMerchant('merchant-x', [
            'name' => 'Boutique',
            'sourceCode' => '4321',
        ]);

        // No exception thrown; soft marker returned so callers never duplicate.
        $this->assertSame('already_exists', $result['status']);
        $this->assertSame('4321', $result['sourceCode']);
    }

    #[Test]
    public function ensure_rethrows_non_conflict_errors(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::errorResponse(400, 'Fail - Bad syntax; domain may be formatted incorrectly'));

        $this->expectException(ApiException::class);
        $resource->ensure(['name' => 'Bad', 'sourceCode' => '1234', 'domain' => 'http://bad']);
    }
}
