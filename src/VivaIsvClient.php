<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv;

use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\Helpers\MerchantWebhookRegistrar;
use QrCommunication\VivaIsv\Resources\ConnectedAccounts;
use QrCommunication\VivaIsv\Resources\EcrTerminals;
use QrCommunication\VivaIsv\Resources\IsvAccounts;
use QrCommunication\VivaIsv\Resources\IsvMessages;
use QrCommunication\VivaIsv\Resources\IsvOrders;
use QrCommunication\VivaIsv\Resources\IsvSources;
use QrCommunication\VivaIsv\Resources\IsvTransactions;
use QrCommunication\VivaIsv\Resources\IsvWebhooks;
use QrCommunication\VivaIsv\Resources\MarketplaceOrders;
use QrCommunication\VivaIsv\Resources\NativeCheckoutIsv;
use QrCommunication\VivaIsv\Resources\Resellers;
use QrCommunication\VivaIsv\Resources\Transfers;
use QrCommunication\VivaIsv\Resources\Webhooks;

/**
 * Viva Wallet ISV SDK — point d'entree principal.
 *
 * Utilisation :
 *
 *     $isv = new VivaIsvClient(
 *         clientId: 'isv-client-id.apps.vivapayments.com',
 *         clientSecret: 'isv-client-secret',
 *         merchantId: 'isv-merchant-uuid',
 *         apiKey: 'isv-api-key',
 *         resellerId: 'reseller-uuid',
 *         resellerApiKey: 'reseller-api-key',
 *         environment: 'demo',
 *     );
 *
 *     // Creer un compte connecte
 *     $account = $isv->accounts->create('email@example.com', 'https://return.url');
 *
 *     // Creer un ordre ISV avec commission
 *     $order = $isv->orders->create(
 *         connectedMerchantId: 'merchant-uuid',
 *         amount: 1500,
 *         isvAmount: 100,
 *     );
 *
 *     // Capturer un preauth avec composite auth
 *     $isv->transactions->capture('txn-uuid', 'merchant-uuid', amount: 1500, isvAmount: 100);
 *
 *     // POS terminal sale
 *     $session = $isv->terminals->sale(terminalId: 16014231, amount: 100, ...);
 *     $result = $isv->terminals->pollUntilComplete($session['session_id']);
 *
 *     // Native checkout ISV
 *     $token = $isv->nativeCheckout->createChargeToken('merchant-uuid', 1500, $paymentData);
 *     $txn = $isv->nativeCheckout->createTransaction('merchant-uuid', $token['chargeToken'], 1500);
 *
 *     // Merchant-level webhook registration (banking events 768/769/2054)
 *     $results = $isv->merchantWebhookRegistrar()->registerAll(
 *         connectedMerchantId: 'merchant-uuid',
 *         callbackUrl: 'https://app.example.com/api/webhooks/viva',
 *     );
 */
final class VivaIsvClient
{
    public readonly ConnectedAccounts $accounts;

    public readonly IsvAccounts $isvAccounts;

    public readonly IsvOrders $orders;

    public readonly IsvTransactions $transactions;

    public readonly EcrTerminals $terminals;

    public readonly Transfers $transfers;

    public readonly MarketplaceOrders $marketplace;

    public readonly NativeCheckoutIsv $nativeCheckout;

    public readonly IsvWebhooks $isvWebhooks;

    public readonly Webhooks $webhooks;

    public readonly IsvMessages $messages;

    public readonly IsvSources $sources;

    public readonly Resellers $resellers;

    private readonly IsvConfig $config;

    private readonly HttpClient $http;

    private ?MerchantWebhookRegistrar $merchantWebhookRegistrarInstance = null;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $merchantId,
        string $apiKey,
        string $resellerId,
        string $resellerApiKey,
        string|Environment $environment = Environment::DEMO,
    ) {
        $this->config = new IsvConfig(
            clientId: $clientId,
            clientSecret: $clientSecret,
            merchantId: $merchantId,
            apiKey: $apiKey,
            resellerId: $resellerId,
            resellerApiKey: $resellerApiKey,
            environment: $environment,
        );

        $this->http = new HttpClient($this->config);

        $this->accounts = new ConnectedAccounts($this->http);
        $this->isvAccounts = new IsvAccounts($this->http);
        $this->orders = new IsvOrders($this->http, $this->config);
        $this->transactions = new IsvTransactions($this->http, $this->config);
        $this->terminals = new EcrTerminals($this->http, $this->config);
        $this->transfers = new Transfers($this->http);
        $this->marketplace = new MarketplaceOrders($this->http, $this->config);
        $this->nativeCheckout = new NativeCheckoutIsv($this->http);
        $this->isvWebhooks = new IsvWebhooks($this->http);
        $this->webhooks = new Webhooks;
        $this->messages = new IsvMessages($this->http, $this->config);
        $this->sources = new IsvSources($this->http);
        $this->resellers = new Resellers($this->http);
    }

    public function getConfig(): IsvConfig
    {
        return $this->config;
    }

    public function invalidateToken(): void
    {
        $this->http->invalidateToken();
    }

    /**
     * Idempotent helper for registering merchant-level webhooks (events 768/769/2054).
     *
     * Lazy-initialized. Treats HTTP 400 "duplicate" from Viva as a success,
     * making it safe to call on every merchant provisioning flow.
     *
     * Usage:
     *
     *     $results = $isv->merchantWebhookRegistrar()->registerAll(
     *         connectedMerchantId: $merchantId,
     *         callbackUrl: 'https://app.example.com/api/webhooks/viva',
     *     );
     */
    public function merchantWebhookRegistrar(): MerchantWebhookRegistrar
    {
        return $this->merchantWebhookRegistrarInstance ??= new MerchantWebhookRegistrar($this->messages);
    }

    /**
     * Test ISV connection by fetching account info.
     */
    public function testConnection(): bool
    {
        try {
            $this->accounts->get($this->config->merchantId);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
