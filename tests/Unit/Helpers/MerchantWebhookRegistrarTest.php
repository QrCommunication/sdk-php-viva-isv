<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\Helpers\MerchantWebhookRegistrar;
use QrCommunication\VivaIsv\IsvConfig;
use QrCommunication\VivaIsv\Resources\IsvMessages;
use QrCommunication\VivaIsv\Tests\Fakes\GuzzleMockFactory;

final class MerchantWebhookRegistrarTest extends TestCase
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
    public function it_registers_all_banking_events_by_default(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $messages = new IsvMessages($http, $this->config);
        $registrar = new MerchantWebhookRegistrar($messages);

        // Queue one success per banking event (768, 769, 2054)
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['MessageId' => 'uuid-768', 'EventTypeId' => 768, 'IsActive' => true]));
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['MessageId' => 'uuid-769', 'EventTypeId' => 769, 'IsActive' => true]));
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['MessageId' => 'uuid-2054', 'EventTypeId' => 2054, 'IsActive' => true]));

        $results = $registrar->registerAll('merchant-abc', 'https://app.example.com/api/webhooks/viva');

        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertSame('created', $result['status']);
        }

        $eventIds = array_column($results, 'event_id');
        $this->assertSame([768, 769, 2054], $eventIds);
    }

    #[Test]
    public function it_treats_duplicate_400_as_already_exists(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $messages = new IsvMessages($http, $this->config);
        $registrar = new MerchantWebhookRegistrar($messages);

        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'duplicate subscription', 3732));
        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'duplicate subscription', 3732));
        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'duplicate subscription', 3732));

        $results = $registrar->registerAll('merchant-abc', 'https://app.example.com/api/webhooks/viva');

        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertSame('already_exists', $result['status']);
            $this->assertArrayNotHasKey('message', $result);
        }
    }

    #[Test]
    public function it_treats_already_message_in_400_as_already_exists(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $messages = new IsvMessages($http, $this->config);
        $registrar = new MerchantWebhookRegistrar($messages);

        // Viva returning "already" in the message text (not error code 3732)
        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'subscription already registered', 0));
        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'subscription already registered', 0));
        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'subscription already registered', 0));

        $results = $registrar->registerAll('merchant-id', 'https://example.com/hook');

        foreach ($results as $result) {
            $this->assertSame('already_exists', $result['status']);
        }
    }

    #[Test]
    public function it_returns_failed_status_on_other_4xx(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $messages = new IsvMessages($http, $this->config);
        $registrar = new MerchantWebhookRegistrar($messages);

        $mockHandler->append(GuzzleMockFactory::errorResponse(403, 'Access denied', 9001));
        $mockHandler->append(GuzzleMockFactory::errorResponse(403, 'Access denied', 9001));
        $mockHandler->append(GuzzleMockFactory::errorResponse(403, 'Access denied', 9001));

        $results = $registrar->registerAll('merchant-abc', 'https://app.example.com/api/webhooks/viva');

        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertSame('failed', $result['status']);
            $this->assertSame('Access denied', $result['message']);
        }
    }

    #[Test]
    public function it_accepts_custom_events_map(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $messages = new IsvMessages($http, $this->config);
        $registrar = new MerchantWebhookRegistrar($messages);

        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['MessageId' => 'uuid', 'IsActive' => true]));

        $results = $registrar->registerAll('merchant-id', 'https://example.com/hook', [768 => 'Bank Transfer Created']);

        $this->assertCount(1, $results);
        $this->assertSame(768, $results[0]['event_id']);
        $this->assertSame('created', $results[0]['status']);
    }

    #[Test]
    public function it_handles_mixed_outcomes_per_event(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $messages = new IsvMessages($http, $this->config);
        $registrar = new MerchantWebhookRegistrar($messages);

        // Event 768: success
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['MessageId' => 'new-uuid', 'IsActive' => true]));
        // Event 769: duplicate
        $mockHandler->append(GuzzleMockFactory::errorResponse(400, 'duplicate subscription', 3732));
        // Event 2054: unexpected failure
        $mockHandler->append(GuzzleMockFactory::errorResponse(503, 'Service unavailable', 0));

        $results = $registrar->registerAll('merchant-xyz', 'https://example.com/hook');

        $this->assertCount(3, $results);

        $byEventId = [];
        foreach ($results as $r) {
            $byEventId[$r['event_id']] = $r;
        }

        $this->assertSame('created', $byEventId[768]['status']);
        $this->assertArrayNotHasKey('message', $byEventId[768]);

        $this->assertSame('already_exists', $byEventId[769]['status']);
        $this->assertArrayNotHasKey('message', $byEventId[769]);

        $this->assertSame('failed', $byEventId[2054]['status']);
        $this->assertSame('Service unavailable', $byEventId[2054]['message']);
    }

    #[Test]
    public function banking_events_constant_contains_expected_ids(): void
    {
        $this->assertArrayHasKey(768, MerchantWebhookRegistrar::BANKING_EVENTS);
        $this->assertArrayHasKey(769, MerchantWebhookRegistrar::BANKING_EVENTS);
        $this->assertArrayHasKey(2054, MerchantWebhookRegistrar::BANKING_EVENTS);
        $this->assertCount(3, MerchantWebhookRegistrar::BANKING_EVENTS);

        foreach (MerchantWebhookRegistrar::BANKING_EVENTS as $eventId => $label) {
            $this->assertIsInt($eventId);
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }
}
