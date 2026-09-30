<?php

declare (strict_types=1);
namespace EDD\Vendor\Square;

use EDD\Vendor\Core\ClientBuilder;
use EDD\Vendor\Core\Request\Parameters\AdditionalHeaderParams;
use EDD\Vendor\Core\Request\Parameters\HeaderParam;
use EDD\Vendor\Core\Request\Parameters\TemplateParam;
use EDD\Vendor\Core\Utils\CoreHelper;
use EDD\Vendor\Square\Apis\CustomersApi;
use EDD\Vendor\Square\Apis\LocationsApi;
use EDD\Vendor\Square\Apis\MerchantsApi;
use EDD\Vendor\Square\Apis\OrdersApi;
use EDD\Vendor\Square\Apis\PaymentsApi;
use EDD\Vendor\Square\Apis\RefundsApi;
use EDD\Vendor\Square\Apis\WebhookSubscriptionsApi;
use EDD\Vendor\Square\Authentication\BearerAuthCredentialsBuilder;
use EDD\Vendor\Square\Authentication\BearerAuthManager;
use EDD\Vendor\Square\Utils\CompatibilityConverter;
use EDD\Vendor\Unirest\Configuration;
use EDD\Vendor\Unirest\HttpClient;
class SquareClient implements ConfigurationInterface
{
    private $mobileAuthorization;
    private $oAuth;
    private $v1Transactions;
    private $applePay;
    private $bankAccounts;
    private $bookings;
    private $bookingCustomAttributes;
    private $cards;
    private $cashDrawers;
    private $catalog;
    private $customers;
    private $customerCustomAttributes;
    private $customerGroups;
    private $customerSegments;
    private $devices;
    private $disputes;
    private $employees;
    private $events;
    private $giftCards;
    private $giftCardActivities;
    private $inventory;
    private $invoices;
    private $labor;
    private $locations;
    private $locationCustomAttributes;
    private $checkout;
    private $transactions;
    private $loyalty;
    private $merchants;
    private $merchantCustomAttributes;
    private $orders;
    private $orderCustomAttributes;
    private $payments;
    private $payouts;
    private $refunds;
    private $sites;
    private $snippets;
    private $subscriptions;
    private $team;
    private $terminal;
    private $vendors;
    private $webhookSubscriptions;
    private $bearerAuthManager;
    private $config;
    private $client;
    /**
     * @see SquareClientBuilder::init()
     * @see SquareClientBuilder::build()
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(ConfigurationDefaults::_ALL, CoreHelper::clone($config));
        $this->bearerAuthManager = new BearerAuthManager($this->config);
        $this->validateConfig();
        $this->client = ClientBuilder::init(new HttpClient(Configuration::init($this)))->converter(new CompatibilityConverter())->jsonHelper(ApiHelper::getJsonHelper())->apiCallback($this->config['httpCallback'] ?? null)->userAgent('Square-PHP-SDK/40.0.0.20250123 ({api-version}) {engine}/{engine-version} ({os-' . 'info}) {detail}')->userAgentConfig(['{api-version}' => $this->getSquareVersion(), '{detail}' => rawurlencode($this->getUserAgentDetail())])->globalConfig($this->getGlobalConfiguration())->globalRuntimeParam(AdditionalHeaderParams::init($this->getAdditionalHeaders()))->serverUrls(self::ENVIRONMENT_MAP[$this->getEnvironment()], Server::DEFAULT_)->authManagers(['global' => $this->bearerAuthManager])->build();
    }
    /**
     * Create a builder with the current client's configurations.
     *
     * @return SquareClientBuilder SquareClientBuilder instance
     */
    public function toBuilder(): SquareClientBuilder
    {
        $builder = SquareClientBuilder::init()->timeout($this->getTimeout())->enableRetries($this->shouldEnableRetries())->numberOfRetries($this->getNumberOfRetries())->retryInterval($this->getRetryInterval())->backOffFactor($this->getBackOffFactor())->maximumRetryWaitTime($this->getMaximumRetryWaitTime())->retryOnTimeout($this->shouldRetryOnTimeout())->httpStatusCodesToRetry($this->getHttpStatusCodesToRetry())->httpMethodsToRetry($this->getHttpMethodsToRetry())->squareVersion($this->getSquareVersion())->additionalHeaders($this->getAdditionalHeaders())->userAgentDetail($this->getUserAgentDetail())->environment($this->getEnvironment())->customUrl($this->getCustomUrl())->httpCallback($this->config['httpCallback'] ?? null);
        $bearerAuth = $this->getBearerAuthCredentialsBuilder();
        if ($bearerAuth != null) {
            $builder->bearerAuthCredentials($bearerAuth);
        }
        return $builder;
    }
    public function getTimeout(): int
    {
        return $this->config['timeout'] ?? ConfigurationDefaults::TIMEOUT;
    }
    public function shouldEnableRetries(): bool
    {
        return $this->config['enableRetries'] ?? ConfigurationDefaults::ENABLE_RETRIES;
    }
    public function getNumberOfRetries(): int
    {
        return $this->config['numberOfRetries'] ?? ConfigurationDefaults::NUMBER_OF_RETRIES;
    }
    public function getRetryInterval(): float
    {
        return $this->config['retryInterval'] ?? ConfigurationDefaults::RETRY_INTERVAL;
    }
    public function getBackOffFactor(): float
    {
        return $this->config['backOffFactor'] ?? ConfigurationDefaults::BACK_OFF_FACTOR;
    }
    public function getMaximumRetryWaitTime(): int
    {
        return $this->config['maximumRetryWaitTime'] ?? ConfigurationDefaults::MAXIMUM_RETRY_WAIT_TIME;
    }
    public function shouldRetryOnTimeout(): bool
    {
        return $this->config['retryOnTimeout'] ?? ConfigurationDefaults::RETRY_ON_TIMEOUT;
    }
    public function getHttpStatusCodesToRetry(): array
    {
        return $this->config['httpStatusCodesToRetry'] ?? ConfigurationDefaults::HTTP_STATUS_CODES_TO_RETRY;
    }
    public function getHttpMethodsToRetry(): array
    {
        return $this->config['httpMethodsToRetry'] ?? ConfigurationDefaults::HTTP_METHODS_TO_RETRY;
    }
    public function getSquareVersion(): string
    {
        return $this->config['squareVersion'] ?? ConfigurationDefaults::SQUARE_VERSION;
    }
    public function getAdditionalHeaders(): array
    {
        return $this->config['additionalHeaders'] ?? ConfigurationDefaults::ADDITIONAL_HEADERS;
    }
    public function getUserAgentDetail(): string
    {
        return $this->config['userAgentDetail'] ?? ConfigurationDefaults::USER_AGENT_DETAIL;
    }
    public function getEnvironment(): string
    {
        return $this->config['environment'] ?? ConfigurationDefaults::ENVIRONMENT;
    }
    public function getCustomUrl(): string
    {
        return $this->config['customUrl'] ?? ConfigurationDefaults::CUSTOM_URL;
    }
    public function getBearerAuthCredentials(): BearerAuthCredentials
    {
        return $this->bearerAuthManager;
    }
    public function getBearerAuthCredentialsBuilder(): ?BearerAuthCredentialsBuilder
    {
        if (empty($this->bearerAuthManager->getAccessToken())) {
            return null;
        }
        return BearerAuthCredentialsBuilder::init($this->bearerAuthManager->getAccessToken());
    }
    /**
     * Get the client configuration as an associative array
     *
     * @see SquareClientBuilder::getConfiguration()
     */
    public function getConfiguration(): array
    {
        return $this->toBuilder()->getConfiguration();
    }
    /**
     * Clone this client and override given configuration options
     *
     * @see SquareClientBuilder::build()
     */
    public function withConfiguration(array $config): self
    {
        return new self(array_merge($this->config, $config));
    }
    /**
     * Get current SDK version
     */
    public function getSdkVersion(): string
    {
        return '40.0.0.20250123';
    }
    /**
     * Validate required configuration variables
     */
    private function validateConfig(): void
    {
        SquareClientBuilder::init()->additionalHeaders($this->getAdditionalHeaders())->userAgentDetail($this->getUserAgentDetail());
    }
    /**
     * Get the base uri for a given server in the current environment.
     *
     * @param string $server Server name
     *
     * @return string Base URI
     */
    public function getBaseUri(string $server = Server::DEFAULT_): string
    {
        return $this->client->getGlobalRequest($server)->getQueryUrl();
    }
    /**
     * Returns Customers Api
     */
    public function getCustomersApi(): CustomersApi
    {
        if ($this->customers == null) {
            $this->customers = new CustomersApi($this->client);
        }
        return $this->customers;
    }
    /**
     * Returns Locations Api
     */
    public function getLocationsApi(): LocationsApi
    {
        if ($this->locations == null) {
            $this->locations = new LocationsApi($this->client);
        }
        return $this->locations;
    }
    /**
     * Returns Merchants Api
     */
    public function getMerchantsApi(): MerchantsApi
    {
        if ($this->merchants == null) {
            $this->merchants = new MerchantsApi($this->client);
        }
        return $this->merchants;
    }
    /**
     * Returns Orders Api
     */
    public function getOrdersApi(): OrdersApi
    {
        if ($this->orders == null) {
            $this->orders = new OrdersApi($this->client);
        }
        return $this->orders;
    }
    /**
     * Returns Payments Api
     */
    public function getPaymentsApi(): PaymentsApi
    {
        if ($this->payments == null) {
            $this->payments = new PaymentsApi($this->client);
        }
        return $this->payments;
    }
    /**
     * Returns Refunds Api
     */
    public function getRefundsApi(): RefundsApi
    {
        if ($this->refunds == null) {
            $this->refunds = new RefundsApi($this->client);
        }
        return $this->refunds;
    }
    /**
     * Returns Webhook Subscriptions Api
     */
    public function getWebhookSubscriptionsApi(): WebhookSubscriptionsApi
    {
        if ($this->webhookSubscriptions == null) {
            $this->webhookSubscriptions = new WebhookSubscriptionsApi($this->client);
        }
        return $this->webhookSubscriptions;
    }
    /**
     * Get the defined global configurations
     */
    private function getGlobalConfiguration(): array
    {
        return [TemplateParam::init('custom_url', $this->getCustomUrl())->dontEncode(), HeaderParam::init('Square-Version', $this->getSquareVersion())];
    }
    /**
     * A map of all base urls used in different environments and servers
     *
     * @var array
     */
    private const ENVIRONMENT_MAP = [Environment::PRODUCTION => [Server::DEFAULT_ => 'https://connect.squareup.com'], Environment::SANDBOX => [Server::DEFAULT_ => 'https://connect.squareupsandbox.com'], Environment::CUSTOM => [Server::DEFAULT_ => '{custom_url}']];
}