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

    private function basicAuthUser(Request $request): string
    {
        $header = $request->getHeaderLine('Authorization');
        $decoded = base64_decode(substr($header, strlen('Basic ')), true) ?: '';

        return $decoded;
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
        // Own account → ISV Basic Auth (merchantId:apiKey), not composite
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
    public function it_lists_own_sources_and_flattens_wrapped_response(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, [
            'Sources' => [
                ['SourceCode' => '1234', 'Name' => 'Web'],
                ['SourceCode' => '5678', 'Name' => 'POS'],
            ],
        ]));

        $sources = $resource->list();

        $this->assertCount(2, $sources);
        $this->assertSame('1234', $sources[0]['SourceCode']);

        $req = $mock->getLastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertStringContainsString('/api/sources', (string) $req->getUri());
        $this->assertSame('isv-merchant-uuid:api-key', $this->basicAuthUser($req));
    }

    #[Test]
    public function it_lists_a_connected_merchant_sources_via_composite_auth(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, [
            ['sourceCode' => '4321', 'name' => 'Boutique'],
        ]));

        $sources = $resource->listForMerchant('merchant-x');

        $this->assertCount(1, $sources);
        $this->assertSame('4321', $sources[0]['sourceCode']);

        $req = $mock->getLastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('reseller-uuid:merchant-x:reseller-api-key', $this->basicAuthUser($req));
    }

    #[Test]
    public function it_finds_a_merchant_source_by_code(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, [
            'Sources' => [['SourceCode' => '4321', 'Name' => 'Boutique']],
        ]));

        $found = $resource->findForMerchant('merchant-x', '4321');
        $this->assertNotNull($found);
        $this->assertSame('Boutique', $found['Name']);
    }

    #[Test]
    public function it_returns_null_when_source_not_found(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, ['Sources' => []]));

        $this->assertNull($resource->findForMerchant('merchant-x', '9999'));
    }

    #[Test]
    public function ensure_for_merchant_returns_existing_without_creating(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        // Only ONE response queued: the list. If a create were issued, the mock
        // queue would be empty and the test would fail — proving no duplicate.
        $mock->append(GuzzleMockFactory::jsonResponse(200, [
            'Sources' => [['SourceCode' => '4321', 'Name' => 'Existing']],
        ]));

        $result = $resource->ensureForMerchant('merchant-x', [
            'name' => 'New attempt',
            'sourceCode' => '4321',
        ]);

        $this->assertSame('Existing', $result['Name']);
        $this->assertSame('GET', $mock->getLastRequest()->getMethod()); // list only, no POST
    }

    #[Test]
    public function ensure_for_merchant_creates_when_absent(): void
    {
        [$http, $mock] = GuzzleMockFactory::create($this->config);
        $resource = new IsvSources($http);

        $mock->append(GuzzleMockFactory::jsonResponse(200, ['Sources' => []]));               // list → empty
        $mock->append(GuzzleMockFactory::jsonResponse(200, ['SourceCode' => '4321', 'name' => 'Created']));

        $result = $resource->ensureForMerchant('merchant-x', [
            'name' => 'Created',
            'sourceCode' => '4321',
            'domain' => 'boutique.fr',
            'pathSuccess' => 'https://boutique.fr/ok',
            'pathFail' => 'https://boutique.fr/ko',
        ]);

        $this->assertSame('Created', $result['name']);
        $this->assertSame('POST', $mock->getLastRequest()->getMethod()); // ended with the create
    }
}
