<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv;

use QrCommunication\VivaIsv\Enums\Environment;
use QrCommunication\VivaIsv\Resources\ConnectedAccounts;
use QrCommunication\VivaIsv\Resources\EcrTerminals;
use QrCommunication\VivaIsv\Resources\IsvOrders;
use QrCommunication\VivaIsv\Resources\IsvTransactions;
use QrCommunication\VivaIsv\Resources\MarketplaceOrders;
use QrCommunication\VivaIsv\Resources\Transfers;
use QrCommunication\VivaIsv\Resources\Webhooks;

/**
 * Viva Wallet ISV SDK — point d'entrée principal.
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
 *     // Créer un compte connecté
 *     $account = $isv->accounts->create('email@example.com', 'https://return.url');
 *
 *     // Créer un ordre ISV avec commission
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
 */
final class VivaIsvClient
{
    public readonly ConnectedAccounts $accounts;

    public readonly IsvOrders $orders;

    public readonly IsvTransactions $transactions;

    public readonly EcrTerminals $terminals;

    public readonly Transfers $transfers;

    public readonly MarketplaceOrders $marketplace;

    public readonly Webhooks $webhooks;

    private readonly IsvConfig $config;

    private readonly HttpClient $http;

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
        $this->orders = new IsvOrders($this->http, $this->config);
        $this->transactions = new IsvTransactions($this->http, $this->config);
        $this->terminals = new EcrTerminals($this->http, $this->config);
        $this->transfers = new Transfers($this->http);
        $this->marketplace = new MarketplaceOrders($this->http, $this->config);
        $this->webhooks = new Webhooks;
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
