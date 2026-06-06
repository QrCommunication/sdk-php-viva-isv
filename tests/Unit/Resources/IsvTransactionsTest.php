<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\IsvTransactions;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class IsvTransactionsTest extends TestCase
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
    public function it_lists_by_clearance_date_via_composite_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvTransactions($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'Transactions' => [['TransactionId' => 'txn-1']],
        ]));

        $result = $resource->listByClearanceDate('merchant-abc', '2026-06-01');

        $this->assertCount(1, $result);
        $this->assertSame('txn-1', $result[0]['TransactionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertStringContainsString('/api/transactions', (string) $lastRequest->getUri());
        $this->assertStringContainsString('clearancedate=2026-06-01', (string) $lastRequest->getUri());
    }

    #[Test]
    public function it_lists_by_order_code_via_composite_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvTransactions($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'Transactions' => [['TransactionId' => 'txn-2']],
        ]));

        $result = $resource->listByOrderCode('merchant-abc', 1234567890);

        $this->assertSame('txn-2', $result[0]['TransactionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertStringContainsString('ordercode=1234567890', (string) $lastRequest->getUri());
    }

    #[Test]
    public function it_lists_by_source_code_via_composite_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvTransactions($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'Transactions' => [['TransactionId' => 'txn-3']],
        ]));

        $result = $resource->listBySourceCode('merchant-abc', '6054', '2026-06-01');

        $this->assertSame('txn-3', $result[0]['TransactionId']);

        $uri = (string) $mockHandler->getLastRequest()->getUri();
        $this->assertStringContainsString('sourcecode=6054', $uri);
        $this->assertStringContainsString('date=2026-06-01', $uri);
    }

    #[Test]
    public function it_makes_a_moto_charge_via_composite_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvTransactions($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'TransactionId' => 'moto-txn-uuid',
            'StatusId' => 'F',
            'Success' => true,
        ]));

        $result = $resource->moto('merchant-abc', [
            'orderCode' => 7909823376998902,
            'isvAmount' => 500,
            'creditcard' => ['number' => '4111111111111111', 'cvc' => '111', 'expirationDate' => '2030-01'],
        ]);

        $this->assertSame('moto-txn-uuid', $result['TransactionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('/api/transactions', (string) $lastRequest->getUri());

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertTrue($body['moto']); // forced to true when absent
        $this->assertSame(7909823376998902, $body['orderCode']);
        $this->assertSame(500, $body['isvAmount']);
    }

    #[Test]
    public function it_increases_preauth_via_bearer_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new IsvTransactions($http, $this->config);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'transactionId' => 'preauth-txn-uuid',
        ]));

        $result = $resource->increasePreauth(
            transactionId: 'preauth-txn-uuid',
            connectedMerchantId: 'merchant-abc',
            amount: 100,
            currencyCode: 978,
            idempotencyKey: 'idem-1',
        );

        $this->assertSame('preauth-txn-uuid', $result['transactionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('/acquiring/v1/isv/transactions/preauth-txn-uuid:increasepreauth', $uri);
        $this->assertStringContainsString('merchantId=merchant-abc', $uri);

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame(100, $body['amount']);
        $this->assertSame('978', $body['currencyCode']);
        $this->assertSame('idem-1', $body['idempotencyKey']);
    }
}
