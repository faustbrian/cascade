---
title: Named Resolvers
description: Manage multiple independent resolution configurations with named resolvers in Cascade
---

Named resolvers allow you to define and manage multiple independent resolution configurations within a single Cascade instance. Each resolver has its own sources, priorities, and transformers.

## Why Named Resolvers?

Use named resolvers when you have different types of values that require different resolution strategies:

- **Credential management** - Different fallback chains for different credential types
- **Feature flags** - Separate resolution for user vs. organization features
- **Configuration** - Different cascades for app config vs. user preferences
- **Multi-tenant** - Isolated resolution per tenant or customer

## Basic Usage

Define multiple resolvers in a single Cascade instance:

```php
use Cline\Cascade\Cascade;

$cascade = new Cascade();

// Define resolver for carrier credentials
$cascade->defineResolver('carrier-credentials')
    ->source('customer', $customerSource)
    ->source('platform', $platformSource);

// Define resolver for feature flags
$cascade->defineResolver('feature-flags')
    ->source('user', $userFlagSource)
    ->source('organization', $orgFlagSource)
    ->source('global', $globalFlagSource);

// Use resolvers by name
$credentials = $cascade->using('carrier-credentials')
    ->get('fedex-api-key', ['customer_id' => 'cust-123']);

$enabled = $cascade->using('feature-flags')
    ->get('dark-mode', ['user_id' => 'user-456']);
```

## Defining Resolvers

### Using Source Objects

Pass source instances directly:

```php
use Cline\Cascade\Source\{CallbackSource, ArraySource};

$cascade->defineResolver('api-config')
    ->source('database', new CallbackSource(
        name: 'db',
        resolver: fn($key) => $this->db->find($key),
    ), priority: 1)
    ->source('defaults', new ArraySource('defaults', [
        'timeout' => 30,
        'retries' => 3,
    ]), priority: 2);
```

### Using Convenience Methods

The resolver builder provides convenience methods:

```php
$cascade->defineResolver('settings')
    // Add callback source
    ->fromCallback(
        name: 'user-db',
        resolver: fn($key, $ctx) => $this->userDb->find($ctx['user_id'], $key),
        supports: fn($key, $ctx) => isset($ctx['user_id']),
        priority: 1,
    )
    // Add array source
    ->fromArray(
        name: 'defaults',
        values: ['theme' => 'light', 'locale' => 'en'],
        priority: 2,
    );
```

## Working with Resolvers

### Retrieving Values

```php
// Get value with default
$value = $cascade->using('settings')
    ->get('theme', ['user_id' => 'user-123'], default: 'light');

// Get with full result metadata
$result = $cascade->using('settings')
    ->resolve('theme', ['user_id' => 'user-123']);

// Require value to exist
$value = $cascade->using('settings')
    ->getOrFail('theme', ['user_id' => 'user-123']);
```

### Adding Transformers

Transform resolved values for a specific resolver:

```php
$cascade->defineResolver('encrypted-credentials')
    ->source('db', $dbSource)
    ->transform(fn($value) => $this->decrypt($value));

// Values are decrypted when resolved
$apiKey = $cascade->using('encrypted-credentials')
    ->get('api-key', ['customer_id' => 'cust-123']);
```

## Resolver Independence

Each resolver maintains its own state:

```php
$cascade = new Cascade();

// Credentials resolver
$cascade->defineResolver('credentials')
    ->fromArray('test', ['api-key' => 'test-key'], priority: 1)
    ->fromArray('prod', ['api-key' => 'prod-key'], priority: 2);

// Config resolver
$cascade->defineResolver('config')
    ->fromArray('test', ['timeout' => 10], priority: 1)
    ->fromArray('prod', ['timeout' => 30], priority: 2);

// Each resolver has independent sources
$apiKey = $cascade->using('credentials')->get('api-key');  // 'test-key'
$timeout = $cascade->using('config')->get('timeout');      // 10
```

## Practical Examples

### Multi-Credential System

Different credential types with different fallback strategies:

```php
class CredentialManager
{
    private Cascade $cascade;

    public function __construct(
        private CustomerRepository $customers,
        private PlatformRepository $platform,
    ) {
        $this->cascade = new Cascade();
        $this->setupResolvers();
    }

    private function setupResolvers(): void
    {
        // Carrier API credentials
        $this->cascade->defineResolver('carrier-api')
            ->fromCallback(
                name: 'customer',
                resolver: fn($carrier, $ctx) =>
                    $this->customers->getCarrierCredentials($ctx['customer_id'], $carrier),
                supports: fn($carrier, $ctx) => isset($ctx['customer_id']),
                priority: 1,
            )
            ->fromCallback(
                name: 'platform',
                resolver: fn($carrier) =>
                    $this->platform->getCarrierCredentials($carrier),
                priority: 2,
            );

        // Payment gateway credentials
        $this->cascade->defineResolver('payment-gateway')
            ->fromCallback(
                name: 'merchant',
                resolver: fn($gateway, $ctx) =>
                    $this->customers->getGatewayCredentials($ctx['merchant_id'], $gateway),
                supports: fn($gateway, $ctx) => isset($ctx['merchant_id']),
                priority: 1,
            )
            ->fromCallback(
                name: 'platform',
                resolver: fn($gateway) =>
                    $this->platform->getGatewayCredentials($gateway),
                priority: 2,
            );

        // OAuth tokens
        $this->cascade->defineResolver('oauth-tokens')
            ->fromCallback(
                name: 'user',
                resolver: fn($service, $ctx) =>
                    $this->customers->getOAuthToken($ctx['user_id'], $service),
                supports: fn($service, $ctx) => isset($ctx['user_id']),
            )
            ->transform(fn($token) => $this->refreshIfExpired($token));
    }

    public function getCarrierCredentials(string $carrier, string $customerId): array
    {
        return $this->cascade->using('carrier-api')
            ->getOrFail($carrier, ['customer_id' => $customerId]);
    }

    public function getPaymentGateway(string $gateway, string $merchantId): array
    {
        return $this->cascade->using('payment-gateway')
            ->getOrFail($gateway, ['merchant_id' => $merchantId]);
    }

    public function getOAuthToken(string $service, string $userId): string
    {
        return $this->cascade->using('oauth-tokens')
            ->getOrFail($service, ['user_id' => $userId]);
    }
}
```

### Feature Flag System

Three-tier feature flag resolution: user → organization → global:

```php
class FeatureFlagService
{
    private Cascade $cascade;

    public function __construct(
        private FlagRepository $flags,
    ) {
        $this->cascade = new Cascade();

        $this->cascade->defineResolver('features')
            // User-specific overrides (highest priority)
            ->fromCallback(
                name: 'user',
                resolver: fn($flag, $ctx) =>
                    $this->flags->getUserFlag($ctx['user_id'], $flag),
                supports: fn($flag, $ctx) => isset($ctx['user_id']),
                priority: 1,
            )
            // Organization-wide settings
            ->fromCallback(
                name: 'organization',
                resolver: fn($flag, $ctx) =>
                    $this->flags->getOrgFlag($ctx['org_id'], $flag),
                supports: fn($flag, $ctx) => isset($ctx['org_id']),
                priority: 2,
            )
            // Global defaults
            ->fromArray(
                name: 'global',
                values: [
                    'dark-mode' => true,
                    'beta-features' => false,
                    'new-dashboard' => false,
                ],
                priority: 3,
            );
    }

    public function isEnabled(string $flag, ?string $userId = null, ?string $orgId = null): bool
    {
        $context = array_filter([
            'user_id' => $userId,
            'org_id' => $orgId,
        ]);

        return (bool) $this->cascade->using('features')
            ->get($flag, $context, default: false);
    }

    public function getSource(string $flag, ?string $userId = null, ?string $orgId = null): string
    {
        $context = array_filter([
            'user_id' => $userId,
            'org_id' => $orgId,
        ]);

        $result = $this->cascade->using('features')
            ->resolve($flag, $context);

        return $result->getSourceName() ?? 'global';
    }
}

// Usage
$flags = new FeatureFlagService($flagRepository);

// Check user-specific flag
$enabled = $flags->isEnabled('dark-mode', userId: 'user-123', orgId: 'org-456');

// Get which level enabled the flag (for analytics)
$source = $flags->getSource('dark-mode', userId: 'user-123', orgId: 'org-456');
// Returns: 'user', 'organization', or 'global'
```

### Multi-Tenant Configuration

Separate resolvers for different configuration types:

```php
class TenantConfigService
{
    private Cascade $cascade;

    public function __construct(
        private ConfigRepository $config,
    ) {
        $this->cascade = new Cascade();
        $this->setupResolvers();
    }

    private function setupResolvers(): void
    {
        // Application settings
        $this->cascade->defineResolver('app-settings')
            ->fromCallback('tenant', fn($key, $ctx) =>
                $this->config->getTenantSetting($ctx['tenant_id'], $key),
                supports: fn($k, $ctx) => isset($ctx['tenant_id']),
                priority: 1,
            )
            ->fromArray('defaults', [
                'session-timeout' => 3600,
                'max-upload-size' => 10485760, // 10MB
            ], priority: 2);

        // Branding/appearance
        $this->cascade->defineResolver('branding')
            ->fromCallback('tenant', fn($key, $ctx) =>
                $this->config->getTenantBranding($ctx['tenant_id'], $key),
                supports: fn($k, $ctx) => isset($ctx['tenant_id']),
                priority: 1,
            )
            ->fromArray('defaults', [
                'logo' => '/assets/default-logo.png',
                'primary-color' => '#3b82f6',
                'font-family' => 'Inter',
            ], priority: 2);

        // Email templates
        $this->cascade->defineResolver('email-templates')
            ->fromCallback('tenant', fn($template, $ctx) =>
                $this->config->getTenantTemplate($ctx['tenant_id'], $template),
                supports: fn($t, $ctx) => isset($ctx['tenant_id']),
                priority: 1,
            )
            ->fromCallback('system', fn($template) =>
                $this->config->getSystemTemplate($template),
                priority: 2,
            );
    }

    public function getAppSetting(string $key, string $tenantId): mixed
    {
        return $this->cascade->using('app-settings')
            ->get($key, ['tenant_id' => $tenantId]);
    }

    public function getBranding(string $key, string $tenantId): mixed
    {
        return $this->cascade->using('branding')
            ->get($key, ['tenant_id' => $tenantId]);
    }

    public function getEmailTemplate(string $template, string $tenantId): string
    {
        return $this->cascade->using('email-templates')
            ->getOrFail($template, ['tenant_id' => $tenantId]);
    }
}
```

## Accessing Resolver Sources

Get information about a resolver's sources:

```php
$resolver = $cascade->using('carrier-credentials');

// Get all sources for inspection
$sources = $resolver->getSources();

foreach ($sources as $source) {
    echo $source->getName() . "\n";
    print_r($source->getMetadata());
}
```

## Default Resolver

The Cascade instance also has a default unnamed resolver:

```php
$cascade = Cascade::from()
    ->fallbackTo($source1)
    ->fallbackTo($source2);

// These are equivalent:
$value = $cascade->get('key');
$value = $cascade->using('default')->get('key');
```

## Best Practices

### 1. Group Related Values

Create resolvers for related value types:

```php
// Good: Separate resolvers for different concerns
$cascade->defineResolver('credentials');
$cascade->defineResolver('feature-flags');
$cascade->defineResolver('user-preferences');

// Avoid: Single resolver for everything
$cascade->defineResolver('everything'); // Too broad
```

### 2. Use Descriptive Names

Choose clear, specific names:

```php
// Good
$cascade->defineResolver('carrier-api-credentials');
$cascade->defineResolver('payment-gateway-config');

// Avoid
$cascade->defineResolver('creds');
$cascade->defineResolver('config'); // Too generic
```

### 3. Document Resolution Order

Make the priority order explicit:

```php
$cascade->defineResolver('api-config')
    ->source('environment', $envSource, priority: 1)    // Highest
    ->source('database', $dbSource, priority: 2)        // Middle
    ->source('defaults', $defaultSource, priority: 3);  // Lowest
```

## Next Steps

- Learn about [Result Metadata](/cascade/result-metadata) to track which source provided values
- Use [Transformers](/cascade/transformers) to modify resolved values per resolver
- Explore [Repositories](/cascade/repositories) to load resolver definitions from config files
- Set up [Events](/cascade/events) for monitoring resolution across resolvers
