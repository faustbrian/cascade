This cookbook provides complete, production-ready examples for common use cases.

## Multi-Tenant Credential Management

Resolve credentials with customer → platform fallback chain:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;
use Psr\SimpleCache\CacheInterface;

class CredentialManager
{
    public function __construct(
        private CustomerCredentialsRepository $customerCreds,
        private PlatformCredentialsRepository $platformCreds,
        private CacheInterface $cache,
    ) {
        $this->setupResolver();
    }

    private function setupResolver(): void
    {
        // Customer-specific credentials (cached)
        $customerSource = new CallbackSource(
            name: 'customer',
            resolver: function(string $carrier, array $context) {
                $creds = $this->customerCreds->find(
                    customerId: $context['customer_id'],
                    carrier: $carrier,
                );
                return $creds ? $this->decryptCredentials($creds) : null;
            },
            supports: fn($carrier, $ctx) => isset($ctx['customer_id']),
        );

        // Platform default credentials
        $platformSource = new CallbackSource(
            name: 'platform',
            resolver: function(string $carrier) {
                $creds = $this->platformCreds->find($carrier);
                return $creds ? $this->decryptCredentials($creds) : null;
            },
        );

        // Build the chain with caching
        Cascade::from($customerSource)
            ->cache($this->cache, ttl: 600, keyGenerator: fn($carrier, $ctx) =>
                "cascade:creds:{$ctx['customer_id']}:{$carrier}"
            )
            ->fallbackTo($platformSource)
            ->cache($this->cache, ttl: 3600)  // Cache platform creds longer
            ->as('carrier-credentials');

        // Track which credentials were used for billing
        Cascade::onResolved(function($event) {
            if ($event->sourceName === 'platform-cached' && isset($event->context['customer_id'])) {
                $this->billing->recordPlatformCredentialUsage(
                    customerId: $event->context['customer_id'],
                    carrier: $event->key,
                );
            }
        });
    }

    public function getCarrierCredentials(string $carrier, string $customerId): array
    {
        return Cascade::using('carrier-credentials')
            ->for(['customer_id' => $customerId])
            ->getOrFail($carrier);
    }

    public function hasCustomerCredentials(string $carrier, string $customerId): bool
    {
        $result = Cascade::using('carrier-credentials')
            ->for(['customer_id' => $customerId])
            ->resolve($carrier);

        return $result->wasFound() && str_starts_with($result->getSourceName(), 'customer');
    }

    private function decryptCredentials(array $encrypted): array
    {
        return [
            'api_key' => $this->decrypt($encrypted['api_key']),
            'api_secret' => $this->decrypt($encrypted['api_secret']),
            'account_number' => $encrypted['account_number'],
            'endpoint' => $encrypted['endpoint'] ?? 'https://api.example.com',
        ];
    }

    private function decrypt(string $value): string
    {
        return openssl_decrypt(
            $value,
            'aes-256-gcm',
            config('app.key'),
            0,
            substr($value, 0, 16)
        );
    }
}

// Usage
$manager = new CredentialManager($customerCreds, $platformCreds, $cache);

// Get customer-specific or platform credentials
$fedexCreds = $manager->getCarrierCredentials('fedex', 'cust-123');

// Use credentials for API call
$shipment = $this->fedex->createShipment($order, $fedexCreds);
```

## Environment-Specific Configuration

Cascade configuration based on environment:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;

class ConfigurationService
{
    public function __construct(
        private string $environment,
        private ConfigRepository $config,
    ) {
        $this->setupResolver();
    }

    private function setupResolver(): void
    {
        // Environment-specific source
        $envSource = new CallbackSource(
            name: "env-{$this->environment}",
            resolver: fn($key) => $this->config->get("{$this->environment}.{$key}"),
        );

        // Shared configuration source
        $sharedSource = new CallbackSource(
            name: 'shared',
            resolver: fn($key) => $this->config->get("shared.{$key}"),
        );

        // Default values
        $defaults = [
            'api-timeout' => 30,
            'max-retries' => 3,
            'cache-ttl' => 300,
            'log-level' => 'info',
            'debug' => false,
        ];

        Cascade::from($envSource)
            ->fallbackTo($sharedSource)
            ->fallbackTo($defaults)
            ->as('app-config');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cascade::using('app-config')->get($key, default: $default);
    }

    public function getAll(array $keys): array
    {
        $results = Cascade::using('app-config')->getMany($keys);
        return array_map(fn($r) => $r->getValue(), $results);
    }

    public function loadAppConfig(): array
    {
        return $this->getAll([
            'api-timeout',
            'max-retries',
            'cache-ttl',
            'log-level',
            'debug',
        ]);
    }
}

// Usage
$config = new ConfigurationService('production', $configRepo);

$timeout = $config->get('api-timeout'); // 30
$debug = $config->get('debug');         // false (production)

$appConfig = $config->loadAppConfig();
```

## Feature Flag System

Three-tier feature flags: user → organization → global:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;
use Psr\SimpleCache\CacheInterface;

class FeatureFlagService
{
    public function __construct(
        private FeatureFlagRepository $flags,
        private CacheInterface $cache,
    ) {
        $this->setupResolver();
    }

    private function setupResolver(): void
    {
        // User-specific overrides (highest priority)
        $userSource = new CallbackSource(
            name: 'user',
            resolver: fn($flag, $ctx) =>
                $this->flags->getUserFlag($ctx['user_id'], $flag),
            supports: fn($flag, $ctx) => isset($ctx['user_id']),
        );

        // Organization-wide settings
        $orgSource = new CallbackSource(
            name: 'organization',
            resolver: fn($flag, $ctx) =>
                $this->flags->getOrgFlag($ctx['org_id'], $flag),
            supports: fn($flag, $ctx) => isset($ctx['org_id']),
        );

        // Global defaults
        $globalDefaults = [
            'dark-mode' => true,
            'beta-features' => false,
            'new-dashboard' => false,
            'advanced-analytics' => false,
            'api-access' => true,
            'export-data' => true,
        ];

        Cascade::from($userSource)
            ->cache($this->cache, ttl: 60, keyGenerator: fn($flag, $ctx) =>
                "flags:user:{$ctx['user_id']}:{$flag}"
            )
            ->fallbackTo($orgSource)
            ->cache($this->cache, ttl: 300, keyGenerator: fn($flag, $ctx) =>
                "flags:org:{$ctx['org_id']}:{$flag}"
            )
            ->fallbackTo($globalDefaults)
            ->cache($this->cache, ttl: 3600)
            ->as('feature-flags');

        // Track which level enabled the flag
        Cascade::onResolved(function($event) {
            $this->analytics->track('feature_flag_resolved', [
                'flag' => $event->key,
                'level' => $event->sourceName,
                'value' => $event->value,
            ]);
        });
    }

    public function isEnabled(
        string $flag,
        ?string $userId = null,
        ?string $orgId = null,
    ): bool {
        $context = array_filter([
            'user_id' => $userId,
            'org_id' => $orgId,
        ]);

        return (bool) Cascade::using('feature-flags')
            ->for($context)
            ->get($flag, default: false);
    }

    public function getEnabledFlags(
        array $flags,
        ?string $userId = null,
        ?string $orgId = null,
    ): array {
        $context = array_filter([
            'user_id' => $userId,
            'org_id' => $orgId,
        ]);

        $results = Cascade::using('feature-flags')
            ->for($context)
            ->getMany($flags);

        $enabled = [];
        foreach ($results as $flag => $result) {
            if ((bool) ($result->getValue() ?? false)) {
                $enabled[] = $flag;
            }
        }

        return $enabled;
    }

    public function getSource(
        string $flag,
        ?string $userId = null,
        ?string $orgId = null,
    ): string {
        $context = array_filter([
            'user_id' => $userId,
            'org_id' => $orgId,
        ]);

        $result = Cascade::using('feature-flags')
            ->for($context)
            ->resolve($flag);

        return $result->getSourceName() ?? 'global';
    }
}

// Usage
$flags = new FeatureFlagService($flagRepo, $cache);

// Check if feature is enabled for user
if ($flags->isEnabled('new-dashboard', userId: 'user-123', orgId: 'org-456')) {
    return view('dashboard.new');
}

// Get all enabled flags for user
$enabled = $flags->getEnabledFlags(
    ['dark-mode', 'beta-features', 'advanced-analytics'],
    userId: 'user-123',
    orgId: 'org-456',
);

// Get which level enabled the flag (for analytics)
$source = $flags->getSource('dark-mode', userId: 'user-123', orgId: 'org-456');
// Returns: 'user-cached', 'organization-cached', or 'global-cached'
```

## Localization with Fallback Chains

Resolve translations with user → tenant → default locale fallback:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;

class LocalizationService
{
    public function __construct(
        private TranslationRepository $translations,
        private string $defaultLocale = 'en',
    ) {
        $this->setupResolver();
    }

    private function setupResolver(): void
    {
        // User's preferred locale
        $userLocaleSource = new CallbackSource(
            name: 'user-locale',
            resolver: function(string $key, array $context) {
                return $this->translations->find(
                    locale: $context['user_locale'],
                    key: $key,
                );
            },
            supports: fn($key, $ctx) => isset($ctx['user_locale']),
        );

        // Tenant's default locale
        $tenantLocaleSource = new CallbackSource(
            name: 'tenant-locale',
            resolver: function(string $key, array $context) {
                return $this->translations->find(
                    locale: $context['tenant_locale'],
                    key: $key,
                );
            },
            supports: fn($key, $ctx) => isset($ctx['tenant_locale']),
        );

        // System default locale
        $defaultLocaleSource = new CallbackSource(
            name: 'default-locale',
            resolver: fn($key) => $this->translations->find(
                locale: $this->defaultLocale,
                key: $key,
            ),
        );

        Cascade::from($userLocaleSource)
            ->fallbackTo($tenantLocaleSource)
            ->fallbackTo($defaultLocaleSource)
            ->as('translations');

        Cascade::onFailed(function($event) {
            // Log missing translations
            $this->logger->warning('Translation missing', [
                'key' => $event->key,
                'attempted_locales' => $event->attemptedSources,
            ]);
        });
    }

    public function translate(
        string $key,
        ?string $userLocale = null,
        ?string $tenantLocale = null,
        array $replacements = [],
    ): string {
        $context = array_filter([
            'user_locale' => $userLocale,
            'tenant_locale' => $tenantLocale,
        ]);

        $translation = Cascade::using('translations')
            ->for($context)
            ->get($key, default: $key);

        // Apply replacements
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(":{$placeholder}", $value, $translation);
        }

        return $translation;
    }

    public function translateMany(
        array $keys,
        ?string $userLocale = null,
        ?string $tenantLocale = null,
    ): array {
        $context = array_filter([
            'user_locale' => $userLocale,
            'tenant_locale' => $tenantLocale,
        ]);

        $results = Cascade::using('translations')
            ->for($context)
            ->getMany($keys);

        return array_map(
            fn($result, $key) => $result->getValue() ?? $key,
            $results,
            array_keys($results)
        );
    }
}

// Usage
$i18n = new LocalizationService($translations);

// Translate with user and tenant locale fallback
$greeting = $i18n->translate(
    'welcome.message',
    userLocale: 'fr',        // Try French first
    tenantLocale: 'de',      // Fall back to German
                             // Finally fall back to English (default)
    replacements: ['name' => 'Alice'],
);

// Translate multiple keys at once
$messages = $i18n->translateMany(
    ['app.title', 'app.description', 'app.welcome'],
    userLocale: 'es',
    tenantLocale: 'en',
);
```

## Model Context Binding

Use Laravel models directly with context binding:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    // Implement toCascadeContext for custom context extraction
    public function toCascadeContext(): array
    {
        return [
            'customer_id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan' => $this->subscription->plan,
            'environment' => config('app.env'),
        ];
    }
}

class CustomerConfigService
{
    public function __construct()
    {
        $this->setupResolver();
    }

    private function setupResolver(): void
    {
        $customerSource = new CallbackSource(
            name: 'customer',
            resolver: fn($key, $ctx) => DB::table('customer_config')
                ->where('customer_id', $ctx['customer_id'])
                ->where('key', $key)
                ->value('value'),
            supports: fn($key, $ctx) => isset($ctx['customer_id']),
        );

        $tenantSource = new CallbackSource(
            name: 'tenant',
            resolver: fn($key, $ctx) => DB::table('tenant_config')
                ->where('tenant_id', $ctx['tenant_id'])
                ->value($key),
            supports: fn($key, $ctx) => isset($ctx['tenant_id']),
        );

        $planSource = new CallbackSource(
            name: 'plan',
            resolver: fn($key, $ctx) => config("plans.{$ctx['plan']}.{$key}"),
            supports: fn($key, $ctx) => isset($ctx['plan']),
        );

        Cascade::from($customerSource)
            ->fallbackTo($tenantSource)
            ->fallbackTo($planSource)
            ->fallbackTo(config('defaults'))
            ->as('customer-config');
    }

    public function getConfig(Customer $customer, string $key, mixed $default = null): mixed
    {
        // Model is automatically converted to context via toCascadeContext()
        return Cascade::using('customer-config')
            ->for($customer)  // Extracts: customer_id, tenant_id, plan, environment
            ->get($key, default: $default);
    }

    public function getBulkConfig(Customer $customer, array $keys): array
    {
        $results = Cascade::using('customer-config')
            ->for($customer)
            ->getMany($keys);

        return array_map(fn($r) => $r->getValue(), $results);
    }
}

// Usage
$customer = Customer::find(123);
$configService = new CustomerConfigService();

// Context automatically extracted from model
$apiLimit = $configService->getConfig($customer, 'api-rate-limit');
$features = $configService->getBulkConfig($customer, [
    'max-users',
    'storage-quota',
    'api-rate-limit',
]);
```

## Shipping Service with CredHub

Complete credential management for shipping carriers:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;

class ShippingCredentialService
{
    public function __construct(
        private CredentialRepository $credentials,
        private CacheInterface $cache,
        private BillingService $billing,
        private AuditLogger $audit,
    ) {
        $this->setupResolver();
    }

    private function setupResolver(): void
    {
        $customerSource = new CallbackSource(
            name: 'customer-credentials',
            resolver: function(string $carrier, array $ctx) {
                $namespace = "/customers/{$ctx['customer_id']}/carriers/{$carrier}";
                $creds = $this->credentials
                    ->where('namespace', $namespace)
                    ->first();

                return $creds?->decrypted_value;
            },
            supports: fn($carrier, $ctx) => isset($ctx['customer_id']),
        );

        $platformSource = new CallbackSource(
            name: 'platform-credentials',
            resolver: function(string $carrier) {
                $namespace = "/platform/carriers/{$carrier}";
                $creds = $this->credentials
                    ->where('namespace', $namespace)
                    ->first();

                return $creds?->decrypted_value;
            },
        );

        Cascade::from($customerSource)
            ->cache($this->cache, ttl: 300)
            ->fallbackTo($platformSource)
            ->cache($this->cache, ttl: 3600)
            ->as('shipping-credentials');

        // Track credential usage for billing and audit
        Cascade::onResolved(function($event) {
            // Bill customer for platform credential usage
            if (str_starts_with($event->sourceName, 'platform') && isset($event->context['customer_id'])) {
                $this->billing->record([
                    'customer_id' => $event->context['customer_id'],
                    'carrier' => $event->key,
                    'source' => $event->sourceName,
                    'billable' => true,
                    'amount' => 0.25, // $0.25 per platform credential use
                ]);
            }

            // Log credential access for audit
            $this->audit->log([
                'event' => 'credential_accessed',
                'carrier' => $event->key,
                'customer_id' => $event->context['customer_id'] ?? null,
                'source' => $event->sourceName,
                'timestamp' => now(),
            ]);
        });
    }

    public function getCarrierCredentials(string $carrier, string $customerId): array
    {
        $result = Cascade::using('shipping-credentials')
            ->for(['customer_id' => $customerId])
            ->resolve($carrier);

        if (!$result->wasFound()) {
            throw new CredentialsNotFoundException(
                "Credentials not found for carrier: {$carrier}"
            );
        }

        return [
            'credentials' => $result->getValue(),
            'source' => $result->getSourceName(),
            'billable' => str_starts_with($result->getSourceName(), 'platform'),
        ];
    }

    public function hasCustomerCredentials(string $carrier, string $customerId): bool
    {
        $result = Cascade::using('shipping-credentials')
            ->for(['customer_id' => $customerId])
            ->resolve($carrier);

        return $result->wasFound() && str_starts_with($result->getSourceName(), 'customer');
    }
}

// Usage in shipping service
class ShippingService
{
    public function __construct(
        private ShippingCredentialService $credService,
        private FedExApi $fedex,
    ) {}

    public function createShipment(Order $order): Shipment
    {
        // Get credentials with automatic customer → platform fallback
        $credResult = $this->credService->getCarrierCredentials(
            'fedex',
            $order->customer_id
        );

        // Create shipment using credentials
        $shipment = $this->fedex->createShipment($order, $credResult['credentials']);

        // Track if customer was billed for platform credentials
        if ($credResult['billable']) {
            $this->metrics->increment('platform_credentials_used', [
                'customer' => $order->customer_id,
                'carrier' => 'fedex',
            ]);
        }

        return $shipment;
    }
}
```

## Next Steps

- Review [Basic Usage](./basic-usage.md) for fundamentals
- Explore [Conductors](./conductors.md) for fluent API patterns
- Use [Events](./events.md) to monitor your implementations
- Check [Advanced Usage](./advanced-usage.md) for optimization patterns
