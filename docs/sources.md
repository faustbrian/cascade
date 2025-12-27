---
title: Sources
description: Complete guide to all source types in Cascade including CallbackSource, ArraySource, CacheSource, ChainedSource, and NullSource
---

Sources are providers that can fetch values from different storage locations. Cascade includes several built-in source types to cover common use cases.

## Source Interface

All sources implement `SourceInterface`:

```php
interface SourceInterface
{
    /** Get the source name */
    public function getName(): string;

    /** Check if this source supports the given key/context */
    public function supports(string $key, array $context): bool;

    /** Attempt to resolve a value (returns null if not found) */
    public function get(string $key, array $context): mixed;

    /** Get metadata about this source */
    public function getMetadata(): array;
}
```

## CallbackSource

The most flexible source type - uses closures to fetch values.

### Basic Usage

```php
use Cline\Cascade\Source\CallbackSource;

$source = new CallbackSource(
    name: 'database',
    resolver: function(string $key, array $context) {
        return $this->db
            ->table('settings')
            ->where('key', $key)
            ->value('value');
    }
);
```

### With Conditional Support

Only query this source when certain conditions are met:

```php
$customerSource = new CallbackSource(
    name: 'customer-db',
    resolver: fn($key, $ctx) => $this->customerDb->find($ctx['customer_id'], $key),
    supports: fn($key, $ctx) => isset($ctx['customer_id']),
);

// Source is skipped when customer_id is not in context
```

### With Value Transformation

Transform values after retrieval:

```php
$encryptedSource = new CallbackSource(
    name: 'encrypted-db',
    resolver: fn($key) => $this->db->find($key),
    transformer: fn($row) => $this->decrypt($row->encrypted_value),
);
```

### Complete Example

```php
$source = new CallbackSource(
    name: 'customer-credentials',
    resolver: function(string $carrier, array $context) {
        return $this->credentials
            ->where('customer_id', $context['customer_id'])
            ->where('carrier', $carrier)
            ->first()?->credentials;
    },
    supports: function(string $carrier, array $context) {
        return isset($context['customer_id'])
            && $this->customers->exists($context['customer_id']);
    },
    transformer: function(array $credentials) {
        return [
            'api_key' => $this->decrypt($credentials['api_key']),
            'api_secret' => $this->decrypt($credentials['api_secret']),
        ];
    },
);
```

## ArraySource

Static in-memory values, perfect for defaults and testing.

### Basic Usage

```php
use Cline\Cascade\Source\ArraySource;

$defaults = new ArraySource('defaults', [
    'api-timeout' => 30,
    'max-retries' => 3,
    'debug' => false,
]);

$cascade = Cascade::from()->fallbackTo($defaults);

$timeout = $cascade->get('api-timeout'); // 30
```

### Nested Arrays

ArraySource supports nested keys:

```php
$config = new ArraySource('config', [
    'database' => [
        'host' => 'localhost',
        'port' => 5432,
    ],
    'cache' => [
        'driver' => 'redis',
        'ttl' => 3600,
    ],
]);

// Access with dot notation
$host = $cascade->get('database.host'); // 'localhost'
$driver = $cascade->get('cache.driver'); // 'redis'
```

### Dynamic Arrays

Build arrays dynamically:

```php
$testCredentials = new ArraySource('test-mode', [
    'fedex' => $this->generateTestCredentials('fedex'),
    'ups' => $this->generateTestCredentials('ups'),
    'dhl' => $this->generateTestCredentials('dhl'),
]);
```

## CacheSource

Decorator that adds PSR-16 caching to any source.

### Basic Caching

```php
use Cline\Cascade\Source\CacheSource;

$dbSource = new CallbackSource(
    name: 'database',
    resolver: fn($key) => $this->db->find($key),
);

$cachedSource = new CacheSource(
    name: 'cached-db',
    inner: $dbSource,
    cache: $this->cache, // PSR-16 CacheInterface
    ttl: 300, // 5 minutes
);
```

### Custom Cache Keys

Generate cache keys based on context:

```php
$cachedSource = new CacheSource(
    name: 'cached-customer-db',
    inner: $customerSource,
    cache: $this->cache,
    ttl: 300,
    keyGenerator: function(string $key, array $context): string {
        return sprintf(
            'cascade:%s:%s:%s',
            $context['customer_id'],
            $context['environment'] ?? 'default',
            $key
        );
    },
);
```

### Different TTL Per Key

```php
$cachedSource = new CacheSource(
    name: 'cached-api',
    inner: $apiSource,
    cache: $this->cache,
    ttl: fn($key) => match($key) {
        'api-key' => 3600,      // 1 hour
        'api-secret' => 86400,  // 24 hours
        default => 300,         // 5 minutes
    },
);
```

### Complete Caching Example

```php
use Psr\SimpleCache\CacheInterface;

class CachedCredentialSource
{
    public function __construct(
        private CallbackSource $inner,
        private CacheInterface $cache,
    ) {}

    public function create(): CacheSource
    {
        return new CacheSource(
            name: 'cached-credentials',
            inner: $this->inner,
            cache: $this->cache,
            ttl: 600, // 10 minutes
            keyGenerator: fn($carrier, $ctx) => sprintf(
                'cascade:credentials:%s:%s',
                $ctx['customer_id'] ?? 'platform',
                $carrier
            ),
        );
    }
}
```

## ChainedSource

Nest a complete cascade as a source (cascade within cascade).

### Basic Nesting

```php
use Cline\Cascade\Source\ChainedSource;

// Inner cascade for tenant resolution
$tenantCascade = Cascade::from()
    ->fallbackTo($tenantSource, priority: 1)
    ->fallbackTo($planSource, priority: 2)
    ->fallbackTo($defaultSource, priority: 3);

// Use as a source in parent cascade
$chainedSource = new ChainedSource(
    name: 'tenant-cascade',
    cascade: $tenantCascade,
);

$parentCascade = Cascade::from()
    ->fallbackTo($customerSource, priority: 1)
    ->fallbackTo($chainedSource, priority: 2);
```

### Multi-Level Hierarchies

Build complex multi-level resolution:

```php
// Level 1: Plan tier defaults
$planCascade = Cascade::from()
    ->fallbackTo($enterprisePlanSource)
    ->fallbackTo($businessPlanSource)
    ->fallbackTo($freePlanSource);

// Level 2: Organization settings with plan fallback
$orgCascade = Cascade::from()
    ->fallbackTo($orgSource, priority: 1)
    ->fallbackTo(new ChainedSource('plan', $planCascade), priority: 2);

// Level 3: User settings with org fallback
$userCascade = Cascade::from()
    ->fallbackTo($userSource, priority: 1)
    ->fallbackTo(new ChainedSource('org', $orgCascade), priority: 2);

// Resolution: user → org → plan (tier) → system defaults
$value = $userCascade->get('feature-limit', [
    'user_id' => 'user-123',
    'org_id' => 'org-456',
    'plan_tier' => 'enterprise',
]);
```

### Conditional Chaining

Chain sources only when context supports it:

```php
$premiumCascade = Cascade::from()
    ->fallbackTo($premiumFeatureSource)
    ->fallbackTo($enhancedLimitSource);

$conditionalChain = new ChainedSource(
    name: 'premium-features',
    cascade: $premiumCascade,
    supports: fn($key, $ctx) => ($ctx['plan'] ?? null) === 'premium',
);

$cascade = Cascade::from()
    ->fallbackTo($standardSource, priority: 1)
    ->fallbackTo($conditionalChain, priority: 2);

// Premium context uses chained source
$value = $cascade->get('rate-limit', ['plan' => 'premium']);
```

## NullSource

Always returns null - useful for testing and placeholders.

### Testing Fallback Chains

```php
use Cline\Cascade\Source\NullSource;

// Test that fallback works when primary source has no value
$cascade = Cascade::from()
    ->fallbackTo(new NullSource('empty-primary'))
    ->fallbackTo(new ArraySource('fallback', ['key' => 'value']));

expect($cascade->get('key'))->toBe('value');
```

### Placeholder Sources

Create sources that will be implemented later:

```php
$cascade = Cascade::from()
    ->fallbackTo(new NullSource('future-api-source'))
    ->fallbackTo($workingSource);

// Application works with fallback until API source is implemented
```

## Custom Sources

Implement `SourceInterface` for custom behavior:

```php
use Cline\Cascade\Source\SourceInterface;

class RedisSource implements SourceInterface
{
    public function __construct(
        private string $name,
        private Redis $redis,
        private string $prefix = 'config:',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(string $key, array $context): bool
    {
        return true; // Always try Redis
    }

    public function get(string $key, array $context): mixed
    {
        $redisKey = $this->prefix . $key;
        $value = $this->redis->get($redisKey);

        return $value !== false ? json_decode($value, true) : null;
    }

    public function getMetadata(): array
    {
        return [
            'type' => 'redis',
            'prefix' => $this->prefix,
        ];
    }
}

// Usage
$redisSource = new RedisSource('redis-config', $redis, 'app:config:');
$cascade = Cascade::from()->fallbackTo($redisSource);
```

### Environment Variable Source

```php
class EnvSource implements SourceInterface
{
    public function __construct(
        private string $name,
        private array $mapping = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(string $key, array $context): bool
    {
        $envKey = $this->mapping[$key] ?? strtoupper($key);
        return isset($_ENV[$envKey]);
    }

    public function get(string $key, array $context): mixed
    {
        $envKey = $this->mapping[$key] ?? strtoupper($key);
        return $_ENV[$envKey] ?? null;
    }

    public function getMetadata(): array
    {
        return ['type' => 'environment'];
    }
}

// Usage
$envSource = new EnvSource('environment', [
    'api-key' => 'APP_API_KEY',
    'api-secret' => 'APP_API_SECRET',
]);
```

## Source Composition

Combine multiple source types for powerful resolution:

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\{CallbackSource, ArraySource, CacheSource};

// Customer database (cached)
$customerDb = new CallbackSource(
    name: 'customer-db',
    resolver: fn($key, $ctx) => $this->customerDb->find($ctx['customer_id'], $key),
    supports: fn($key, $ctx) => isset($ctx['customer_id']),
);

$cachedCustomerDb = new CacheSource(
    name: 'cached-customer',
    inner: $customerDb,
    cache: $this->cache,
    ttl: 300,
);

// Platform API (cached)
$platformApi = new CallbackSource(
    name: 'platform-api',
    resolver: fn($key) => $this->platformApi->getConfig($key),
);

$cachedPlatformApi = new CacheSource(
    name: 'cached-platform',
    inner: $platformApi,
    cache: $this->cache,
    ttl: 600,
);

// Static defaults
$defaults = new ArraySource('defaults', [
    'timeout' => 30,
    'retries' => 3,
]);

// Build cascade
$cascade = Cascade::from()
    ->fallbackTo($cachedCustomerDb, priority: 1)
    ->fallbackTo($cachedPlatformApi, priority: 2)
    ->fallbackTo($defaults, priority: 3);
```

## Next Steps

- Learn about [Named Resolvers](/cascade/named-resolvers) for managing multiple source configurations
- Explore [Result Metadata](/cascade/result-metadata) to track which source provided values
- Use [Transformers](/cascade/transformers) to modify resolved values
- Set up [Events](/cascade/events) for monitoring source queries
