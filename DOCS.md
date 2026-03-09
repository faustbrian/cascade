## Table of Contents

1. [Overview](#doc-docs-readme)
2. [Advanced Usage](#doc-docs-advanced-usage)
3. [Basic Usage](#doc-docs-basic-usage)
4. [Bulk Resolution](#doc-docs-bulk-resolution)
5. [Conductors](#doc-docs-conductors)
6. [Cookbook](#doc-docs-cookbook)
7. [Events](#doc-docs-events)
8. [Named Resolvers](#doc-docs-named-resolvers)
9. [Repositories](#doc-docs-repositories)
10. [Result Metadata](#doc-docs-result-metadata)
11. [Sources](#doc-docs-sources)
12. [Transformers](#doc-docs-transformers)
<a id="doc-docs-readme"></a>

## Installation

Install the package via Composer:

```bash
composer require cline/cascade
```

## What is Cascade?

Cascade is a framework-agnostic resolver that fetches values from multiple sources in priority order, returning the first match. It's perfect for implementing cascading lookups where you want customer-specific → tenant-specific → platform-default fallback chains.

**Think:** "Get the FedEx credentials for this customer, falling back to platform defaults if they don't have their own."

## Quick Start

### Basic Usage

```php
use Cline\Cascade\Cascade;

// Create a source chain
$timeout = Cascade::from(['api-timeout' => 30, 'max-retries' => 3])
    ->get('api-timeout'); // 30

$retries = Cascade::from(['api-timeout' => 30, 'max-retries' => 3])
    ->get('max-retries'); // 3

$missing = Cascade::from(['api-timeout' => 30])
    ->get('missing-key'); // null
```

### With Fallback Chain

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;

// Register as named resolver
Cascade::from(new CallbackSource(
    name: 'customer',
    resolver: fn($key, $ctx) => $customerDb->find($ctx['customer_id'], $key),
    supports: fn($key, $ctx) => isset($ctx['customer_id']),
))
    ->fallbackTo(new CallbackSource(
        name: 'platform',
        resolver: fn($key) => $platformDb->find($key),
    ))
    ->as('credentials');

// Resolve with context
$apiKey = Cascade::using('credentials')
    ->for(['customer_id' => 'cust-123'])
    ->get('fedex-api-key');
// Tries customer source first, falls back to platform if not found
```

## Core Concepts

### Sources

Providers that can fetch values from different locations:
- **Database queries**
- **API calls**
- **Cache layers**
- **Config files**
- **Environment variables**
- **In-memory stores**

### Resolution Chain

Ordered list of sources to try:
```
Customer credentials → Platform credentials → null
User settings → Org settings → Defaults → null
```

### Resolution Context

Variables available during resolution:
```php
['customer_id' => 'cust-123', 'environment' => 'production']
```

### Priority Ordering

Lower priority values are queried first:
- Priority `1` is checked before priority `10`
- Default priority is `0`
- Negative priorities are supported

## Common Use Cases

1. **Credential Resolution** - Customer credentials → Platform credentials
2. **Configuration Cascading** - Environment → Tenant → Application defaults
3. **Feature Flags** - User → Organization → Global flags
4. **Tenant Settings** - Customer → Plan tier → Platform defaults
5. **Localization** - User locale → Tenant locale → Default locale
6. **Asset Resolution** - Theme → Brand → Default assets

## Next Steps

- Learn about [Basic Usage](#doc-docs-basic-usage) for detailed examples
- Explore [Sources](#doc-docs-sources) to understand different source types
- Check out [Named Resolvers](#doc-docs-named-resolvers) for managing multiple configurations
- See [Events](#doc-docs-events) for monitoring resolution lifecycle

<a id="doc-docs-advanced-usage"></a>

This guide covers advanced patterns and techniques for building sophisticated resolution systems with Cascade.

## Conditional Source Resolution

### Context-Aware Source Selection

Sources can conditionally participate based on complex context checks:

```php
use Cline\Cascade\Source\CallbackSource;

$premiumSource = new CallbackSource(
    name: 'premium-features',
    resolver: fn($key, $ctx) => $this->premiumDb->find($key, $ctx['customer_id']),
    supports: function(string $key, array $context): bool {
        // Only for premium customers
        if (!isset($context['customer_id'])) {
            return false;
        }

        $customer = $this->customers->find($context['customer_id']);
        return $customer?->plan === 'premium';
    },
);

$standardSource = new CallbackSource(
    name: 'standard-features',
    resolver: fn($key, $ctx) => $this->standardDb->find($key),
);

$cascade = Cascade::from()
    ->fallbackTo($premiumSource, priority: 1)
    ->fallbackTo($standardSource, priority: 2);

// Premium customers get premium features, others get standard
$features = $cascade->get('rate-limit', ['customer_id' => 'cust-123']);
```

### Time-Based Sources

Sources that only apply during certain time periods:

```php
$businessHoursSource = new CallbackSource(
    name: 'business-hours-support',
    resolver: fn($key) => $this->supportDb->find($key),
    supports: function(string $key, array $context): bool {
        $now = now();
        $start = $now->copy()->setTime(9, 0);
        $end = $now->copy()->setTime(17, 0);

        return $now->between($start, $end) && $now->isWeekday();
    },
);

$afterHoursSource = new CallbackSource(
    name: 'after-hours-support',
    resolver: fn($key) => $this->emergencyDb->find($key),
);

$cascade = Cascade::from()
    ->fallbackTo($businessHoursSource, priority: 1)
    ->fallbackTo($afterHoursSource, priority: 2);
```

### Feature Flag Gating

Sources enabled by feature flags:

```php
$experimentalSource = new CallbackSource(
    name: 'experimental-api',
    resolver: fn($key) => $this->experimentalApi->get($key),
    supports: fn($key, $ctx) =>
        $this->featureFlags->isEnabled('use-experimental-api', $ctx['user_id'] ?? null),
);

$stableSource = new CallbackSource(
    name: 'stable-api',
    resolver: fn($key) => $this->stableApi->get($key),
);
```

## Advanced Caching Strategies

### Context-Aware Cache Keys

Generate cache keys based on context for multi-tenant caching:

```php
use Cline\Cascade\Source\CacheSource;

$cachedSource = new CacheSource(
    name: 'cached-credentials',
    inner: $dbSource,
    cache: $this->cache,
    ttl: 600,
    keyGenerator: function(string $key, array $context): string {
        // Include all relevant context in cache key
        $parts = [
            'cascade',
            $context['customer_id'] ?? 'global',
            $context['environment'] ?? 'production',
            $key,
        ];

        return implode(':', $parts);
    },
);

// Each customer/environment gets isolated cache
$creds = $cascade->get('api-key', [
    'customer_id' => 'cust-123',
    'environment' => 'production',
]);
// Cache key: cascade:cust-123:production:api-key
```

### Selective Caching

Cache only expensive operations:

```php
class SelectiveCacheSource extends CallbackSource
{
    public function __construct(
        string $name,
        callable $resolver,
        private CacheInterface $cache,
        private array $cachedKeys = [],
        private int $ttl = 300,
    ) {
        parent::__construct($name, $resolver);
    }

    public function get(string $key, array $context): mixed
    {
        // Only cache specific keys
        if (!in_array($key, $this->cachedKeys)) {
            return parent::get($key, $context);
        }

        $cacheKey = $this->makeCacheKey($key, $context);

        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $value = parent::get($key, $context);

        if ($value !== null) {
            $this->cache->set($cacheKey, $value, $this->ttl);
        }

        return $value;
    }
}

$source = new SelectiveCacheSource(
    name: 'selective-cache',
    resolver: fn($key) => $this->db->find($key),
    cache: $cache,
    cachedKeys: ['expensive-query', 'slow-api-call'], // Only cache these
    ttl: 600,
);
```

### Tiered Caching

Multiple cache layers (memory → Redis → database):

```php
class TieredCacheSource implements SourceInterface
{
    private array $memoryCache = [];

    public function __construct(
        private string $name,
        private SourceInterface $inner,
        private CacheInterface $redisCache,
        private int $redisTtl = 600,
        private int $memoryTtl = 60,
    ) {}

    public function get(string $key, array $context): mixed
    {
        $cacheKey = $this->makeCacheKey($key, $context);

        // Layer 1: Memory cache
        if (isset($this->memoryCache[$cacheKey])) {
            if ($this->memoryCache[$cacheKey]['expires'] > time()) {
                return $this->memoryCache[$cacheKey]['value'];
            }
            unset($this->memoryCache[$cacheKey]);
        }

        // Layer 2: Redis cache
        if ($cached = $this->redisCache->get($cacheKey)) {
            $this->memoryCache[$cacheKey] = [
                'value' => $cached,
                'expires' => time() + $this->memoryTtl,
            ];
            return $cached;
        }

        // Layer 3: Source
        $value = $this->inner->get($key, $context);

        if ($value !== null) {
            // Store in both caches
            $this->redisCache->set($cacheKey, $value, $this->redisTtl);
            $this->memoryCache[$cacheKey] = [
                'value' => $value,
                'expires' => time() + $this->memoryTtl,
            ];
        }

        return $value;
    }
}
```

## Nested Chained Sources

### Multi-Level Resolution Hierarchies

Build complex multi-level fallback chains:

```php
use Cline\Cascade\Source\ChainedSource;

// Level 1: User preferences
$userCascade = Cascade::from()
    ->fallbackTo($userDbSource, priority: 1)
    ->fallbackTo($userDefaultsSource, priority: 2);

// Level 2: Organization settings
$orgCascade = Cascade::from()
    ->fallbackTo($orgDbSource, priority: 1)
    ->fallbackTo($orgDefaultsSource, priority: 2)
    ->fallbackTo(new ChainedSource('user', $userCascade), priority: 3);

// Level 3: Application settings
$appCascade = Cascade::from()
    ->fallbackTo($appDbSource, priority: 1)
    ->fallbackTo(new ChainedSource('org', $orgCascade), priority: 2)
    ->fallbackTo($systemDefaultsSource, priority: 3);

// Resolution path: app → app-defaults → org → org-defaults → user → user-defaults → system
$value = $appCascade->get('feature-limit', [
    'user_id' => 'user-123',
    'org_id' => 'org-456',
]);
```

### Conditional Chaining

Only use chained sources when conditions are met:

```php
// Enterprise features cascade (only for enterprise customers)
$enterpriseCascade = Cascade::from()
    ->fallbackTo($enterpriseSource)
    ->fallbackTo($premiumSource);

$conditionalChain = new ChainedSource(
    name: 'enterprise-features',
    cascade: $enterpriseCascade,
    supports: fn($key, $ctx) =>
        isset($ctx['plan']) && in_array($ctx['plan'], ['enterprise', 'premium']),
);

$mainCascade = Cascade::from()
    ->fallbackTo($standardSource, priority: 1)
    ->fallbackTo($conditionalChain, priority: 2);

// Enterprise customers get enterprise cascade, others skip it
$value = $mainCascade->get('advanced-analytics', ['plan' => 'enterprise']);
```

## Repository Chains

### Environment-Based Repository Selection

```php
use Cline\Cascade\Repository\{ChainedRepository, JsonRepository, DatabaseRepository};

class EnvironmentAwareRepositoryFactory
{
    public function create(string $environment): ChainedRepository
    {
        $repositories = [];

        // Local overrides in development
        if ($environment === 'local') {
            $repositories[] = new JsonRepository('/app/local-overrides.json');
        }

        // Environment-specific configuration
        if (file_exists("/etc/cascade/{$environment}.json")) {
            $repositories[] = new JsonRepository("/etc/cascade/{$environment}.json");
        }

        // Shared database configuration
        $repositories[] = new CachedRepository(
            inner: new DatabaseRepository($this->pdo, 'resolvers'),
            cache: $this->cache,
            ttl: 600,
        );

        // System defaults
        $repositories[] = new JsonRepository('/etc/cascade/defaults.json');

        return new ChainedRepository($repositories);
    }
}
```

### Multi-Tenant Repository Isolation

```php
class TenantRepository implements ResolverRepositoryInterface
{
    public function __construct(
        private DatabaseRepository $database,
        private string $tenantId,
    ) {}

    public function get(string $name): array
    {
        // Try tenant-specific resolver first
        $tenantName = "{$this->tenantId}:{$name}";

        if ($this->database->has($tenantName)) {
            return $this->database->get($tenantName);
        }

        // Fall back to shared resolver
        return $this->database->get($name);
    }

    // ... implement other methods
}

// Usage: Each tenant gets isolated resolvers
$tenantRepo = new TenantRepository($dbRepository, 'tenant-456');
$cascade = Cascade::withRepository($tenantRepo);
```

## Custom Source Implementations

### Retry Source

Automatically retry failed source queries:

```php
class RetrySource implements SourceInterface
{
    public function __construct(
        private string $name,
        private SourceInterface $inner,
        private int $maxRetries = 3,
        private int $retryDelayMs = 100,
    ) {}

    public function get(string $key, array $context): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $this->inner->get($key, $context);
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelayMs * 1000 * $attempt); // Exponential backoff
                }
            }
        }

        $this->logger->error("Source failed after {$this->maxRetries} retries", [
            'source' => $this->name,
            'key' => $key,
            'error' => $lastException->getMessage(),
        ]);

        return null;
    }

    // ... implement other methods
}

$retrySource = new RetrySource('api-with-retry', $apiSource, maxRetries: 3);
```

### Circuit Breaker Source

Prevent cascading failures with circuit breaker pattern:

```php
class CircuitBreakerSource implements SourceInterface
{
    private int $failures = 0;
    private ?float $openedAt = null;
    private const THRESHOLD = 5;
    private const TIMEOUT = 60; // seconds

    public function __construct(
        private string $name,
        private SourceInterface $inner,
    ) {}

    public function get(string $key, array $context): mixed
    {
        // Check if circuit is open
        if ($this->isOpen()) {
            // Try to close after timeout
            if ($this->shouldAttemptReset()) {
                $this->openedAt = null;
            } else {
                return null; // Fail fast
            }
        }

        try {
            $value = $this->inner->get($key, $context);
            $this->onSuccess();
            return $value;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        return $this->openedAt !== null;
    }

    private function shouldAttemptReset(): bool
    {
        return $this->openedAt !== null
            && (microtime(true) - $this->openedAt) > self::TIMEOUT;
    }

    private function onSuccess(): void
    {
        $this->failures = 0;
    }

    private function onFailure(): void
    {
        $this->failures++;

        if ($this->failures >= self::THRESHOLD) {
            $this->openedAt = microtime(true);
        }
    }

    // ... implement other methods
}
```

### Fallback Source

Provide a fallback value when source fails:

```php
class FallbackSource implements SourceInterface
{
    public function __construct(
        private string $name,
        private SourceInterface $primary,
        private mixed $fallbackValue,
    ) {}

    public function get(string $key, array $context): mixed
    {
        try {
            $value = $this->primary->get($key, $context);
            return $value ?? $this->fallbackValue;
        } catch (\Throwable $e) {
            $this->logger->warning("Source failed, using fallback", [
                'source' => $this->name,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return is_callable($this->fallbackValue)
                ? ($this->fallbackValue)($key, $context, $e)
                : $this->fallbackValue;
        }
    }

    // ... implement other methods
}
```

## Advanced Transformation Patterns

### Lazy Value Transformation

Defer expensive transformations until needed:

```php
class LazyValue
{
    private mixed $transformed = null;
    private bool $isTransformed = false;

    public function __construct(
        private mixed $raw,
        private \Closure $transformer,
    ) {}

    public function get(): mixed
    {
        if (!$this->isTransformed) {
            $this->transformed = ($this->transformer)($this->raw);
            $this->isTransformed = true;
        }

        return $this->transformed;
    }
}

$cascade = Cascade::from()
    ->fallbackTo($source)
    ->transform(fn($value) => new LazyValue($value, fn($v) => $this->expensiveTransform($v)));

$result = $cascade->get('key');
$value = $result->get(); // Transformation happens here
```

### Composite Transformers

Chain multiple transformers:

```php
class CompositeTransformer
{
    public function __construct(
        private array $transformers,
    ) {}

    public function transform(mixed $value): mixed
    {
        return array_reduce(
            $this->transformers,
            fn($carry, $transformer) => $transformer($carry),
            $value
        );
    }
}

$transformer = new CompositeTransformer([
    fn($v) => json_decode($v, true),        // Parse JSON
    fn($v) => $this->decrypt($v),            // Decrypt
    fn($v) => new Credentials($v),           // Create object
    fn($v) => $v->validate(),                // Validate
]);

$cascade = Cascade::from()
    ->fallbackTo($source)
    ->transform(fn($v) => $transformer->transform($v));
```

## Performance Optimization

### Batch Source Optimization

Optimize sources to handle batch queries:

```php
class BatchOptimizedSource implements SourceInterface
{
    private array $pendingKeys = [];
    private array $results = [];

    public function get(string $key, array $context): mixed
    {
        // Queue key for batch query
        $this->pendingKeys[] = $key;

        // If we've accumulated enough keys, execute batch query
        if (count($this->pendingKeys) >= 10) {
            $this->executeBatch();
        }

        return $this->results[$key] ?? null;
    }

    private function executeBatch(): void
    {
        if (empty($this->pendingKeys)) {
            return;
        }

        // Single database query for all pending keys
        $rows = $this->db
            ->whereIn('key', $this->pendingKeys)
            ->get();

        foreach ($rows as $row) {
            $this->results[$row->key] = $row->value;
        }

        $this->pendingKeys = [];
    }

    // ... implement other methods
}
```

### Prefetching

Prefetch likely-needed values:

```php
class PrefetchingCascade
{
    public function __construct(
        private Cascade $cascade,
    ) {}

    public function getWithRelated(string $key, array $relatedKeys, array $context = []): array
    {
        // Prefetch all related keys
        $allKeys = array_merge([$key], $relatedKeys);
        $results = $this->cascade->getMany($allKeys, $context);

        return [
            'primary' => $results[$key]->getValue(),
            'related' => array_map(
                fn($r) => $r->getValue(),
                array_filter($results, fn($k) => $k !== $key, ARRAY_FILTER_USE_KEY)
            ),
        ];
    }
}
```

## Next Steps

- Apply these patterns in [Cookbook](#doc-docs-cookbook) recipes
- Use advanced sources with [Events](#doc-docs-events) for monitoring
- Combine with [Repositories](#doc-docs-repositories) for dynamic configuration
- Explore [Bulk Resolution](#doc-docs-bulk-resolution) for batch optimization

<a id="doc-docs-basic-usage"></a>

This guide covers the essential patterns for using Cascade in your application.

## Simple Resolution

The most basic usage creates a source chain and resolves values:

```php
use Cline\Cascade\Cascade;

// Inline array source
$timeout = Cascade::from(['api-timeout' => 30])
    ->get('api-timeout'); // 30

// Named source for reuse
Cascade::from([
    'api-timeout' => 30,
    'max-retries' => 3,
    'debug' => false,
])->as('config');

// Resolve from named resolver
$timeout = Cascade::using('config')->get('api-timeout'); // 30
$retries = Cascade::using('config')->get('max-retries'); // 3
$debug = Cascade::using('config')->get('debug');        // false
```

## Default Values

When a key is not found in any source, you can provide a default:

```php
// Simple default value
$timeout = Cascade::from(['some-key' => 'value'])
    ->get('api-timeout', default: 30);

// Default using a closure
$apiKey = Cascade::from($source)
    ->get('api-key', default: fn() => $this->generateKey());

// No default returns null
$missing = Cascade::from($source)->get('missing-key'); // null
```

### Default Value Factory

Use closures for expensive defaults that should only be computed when needed:

```php
$credentials = Cascade::from($source)->get('oauth-token', default: function() {
    // Only called if not found in any source
    return $this->oauth->refreshToken();
});
```

## Fallback Chains

Build cascading resolution with `fallbackTo()`:

```php
use Cline\Cascade\Source\CallbackSource;

$apiKey = Cascade::from(new CallbackSource(
    name: 'customer-db',
    resolver: fn($key, $ctx) => $this->customerDb->find($ctx['customer_id'], $key),
))
    ->fallbackTo(new CallbackSource(
        name: 'platform-defaults',
        resolver: fn($key) => $this->platformDb->find($key),
    ))
    ->get('api-key', context: ['customer_id' => 123]);
// Tries customer-db first, falls back to platform-defaults if not found
```

### Auto-Incrementing Priorities

`fallbackTo()` automatically assigns increasing priorities:

```php
$value = Cascade::from($primary)      // priority: 0
    ->fallbackTo($secondary)          // priority: 10
    ->fallbackTo($tertiary)           // priority: 20
    ->get('key');
// Checks: $primary → $secondary → $tertiary
```

## Resolution with Context

### Named Resolver with Context

```php
use Cline\Cascade\Source\CallbackSource;

// Register resolver
Cascade::from(new CallbackSource(
    name: 'customer-settings',
    resolver: function(string $key, array $context) {
        return $this->db
            ->table('customer_settings')
            ->where('customer_id', $context['customer_id'])
            ->where('key', $key)
            ->value('value');
    }
))->as('settings');

// Resolve with context
$apiKey = Cascade::using('settings')
    ->for(['customer_id' => 'cust-123', 'environment' => 'production'])
    ->get('api-key');
```

### Direct Context (No Named Resolver)

```php
$apiKey = Cascade::from(new CallbackSource(
    name: 'customer-db',
    resolver: fn($key, $ctx) => $this->db->find($ctx['customer_id'], $key),
))->get('api-key', context: ['customer_id' => 123]);
```

### Model Context Binding

Models with `getKey()` are automatically converted to context:

```php
class Customer extends Model {
    public function getKey(): int {
        return $this->id; // 123
    }
}

Cascade::from($source)->as('credentials');

$customer = Customer::find(123);

// Automatically extracts 'customer_id' => 123
$value = Cascade::using('credentials')
    ->for($customer)
    ->get('api-key');
```

### Custom Context Extraction

Implement `toCascadeContext()` for custom context:

```php
class Customer extends Model {
    public function toCascadeContext(): array {
        return [
            'customer_id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'environment' => config('app.env'),
        ];
    }
}

$customer = Customer::find(123);

$value = Cascade::using('credentials')
    ->for($customer)
    ->get('api-key');
```

### Context Best Practices

Structure context to be specific and type-safe:

```php
// Good: Specific keys
$result = Cascade::using('credentials')
    ->for([
        'customer_id' => 'cust-123',
        'environment' => 'production',
    ])
    ->get('stripe-key');

// Better: Use value objects
readonly class ResolutionContext {
    public function __construct(
        public CustomerId $customerId,
        public Environment $environment,
    ) {}

    public function toArray(): array {
        return [
            'customer_id' => $this->customerId->value,
            'environment' => $this->environment->value,
        ];
    }
}

$context = new ResolutionContext(
    customerId: CustomerId::from('cust-123'),
    environment: Environment::PRODUCTION,
);

$result = Cascade::using('credentials')
    ->for($context->toArray())
    ->get('stripe-key');
```

## Conditional Sources

Sources can declare which keys they support:

```php
use Cline\Cascade\Source\CallbackSource;

// Production-only source
$prodSource = new CallbackSource(
    name: 'production',
    resolver: fn($key) => $this->prodConfig($key),
    supports: fn($key, $ctx) => $ctx['environment'] === 'production',
);

// Development fallback
$devSource = new CallbackSource(
    name: 'development',
    resolver: fn($key) => $this->devConfig($key),
);

Cascade::from($prodSource)
    ->fallbackTo($devSource)
    ->as('env-config');

// In production, uses $prodSource; otherwise uses $devSource
$value = Cascade::using('env-config')
    ->for(['environment' => app()->environment()])
    ->get('api-endpoint');
```

## Transformers

Apply transformations to resolved values:

```php
// Single transformer
$name = Cascade::from(['name' => 'john'])
    ->transform(fn($v) => strtoupper($v))
    ->get('name'); // 'JOHN'

// Multiple transformers (applied in order)
$settings = Cascade::from(['settings' => '{"theme":"dark"}'])
    ->transform(fn($v) => json_decode($v, true))
    ->transform(fn($v) => new SettingsDto($v))
    ->get('settings'); // SettingsDto instance

// With named resolvers
$value = Cascade::using('config')
    ->transform(fn($v) => (int) $v)
    ->transform(fn($v) => $v * 1.1)
    ->get('price');
```

Transformers receive the source as second argument:

```php
$value = Cascade::from($source)
    ->transform(function($value, $source) {
        logger()->info("Resolved from: {$source->getName()}");
        return $value;
    })
    ->get('key');
```

## Direct vs Named Resolvers

### Direct Resolution (Anonymous)

Use for one-off queries:

```php
$value = Cascade::from($source1)
    ->fallbackTo($source2)
    ->get('key');
```

### Named Resolvers (Reusable)

Use for frequently accessed configurations:

```php
// Define once
Cascade::from($source1)
    ->fallbackTo($source2)
    ->as('my-resolver');

// Use many times
$value1 = Cascade::using('my-resolver')->get('key1');
$value2 = Cascade::using('my-resolver')->get('key2');
$value3 = Cascade::using('my-resolver')->for($customer)->get('key3');
```

## Complete Example

Multi-tenant configuration with fallback and caching:

```php
use Psr\SimpleCache\CacheInterface;
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;

// Define the resolver
Cascade::from(new CallbackSource(
    name: 'customer-config',
    resolver: fn($k, $ctx) => DB::table('customer_config')
        ->where('customer_id', $ctx['customer_id'])
        ->where('key', $k)
        ->value('value'),
    supports: fn($k, $ctx) => isset($ctx['customer_id']),
))
    ->cache(app(CacheInterface::class), ttl: 300)  // Cache customer config
    ->fallbackTo(new CallbackSource(
        name: 'tenant-config',
        resolver: fn($k, $ctx) => DB::table('tenant_config')
            ->where('tenant_id', $ctx['tenant_id'])
            ->value($k),
        supports: fn($k, $ctx) => isset($ctx['tenant_id']),
    ))
    ->fallbackTo(config('defaults'))  // Array fallback
    ->as('app-config');

// Use throughout application
class SomeService {
    public function processOrder(Order $order) {
        $customer = $order->customer;

        $maxRetries = Cascade::using('app-config')
            ->for($customer)
            ->transform(fn($v) => (int) $v)
            ->get('max-retries', default: 3);

        // Process with customer-specific or tenant-specific or default config
    }
}
```

## Next Steps

- [Conductors](#doc-docs-conductors) - Deep dive into conductor patterns
- [Sources](#doc-docs-sources) - Built-in source types
- [Named Resolvers](#doc-docs-named-resolvers) - Managing multiple resolvers
- [Events](#doc-docs-events) - Monitoring resolution lifecycle

<a id="doc-docs-bulk-resolution"></a>

Cascade supports resolving multiple keys in a single operation, which is more efficient than calling `get()` repeatedly. Use `getMany()` when you need to fetch several related values.

## Basic Usage

Resolve multiple keys with the same context:

```php
use Cline\Cascade\Cascade;

$cascade = Cascade::from()
    ->fallbackTo($source);

// Resolve multiple keys at once
$results = $cascade->getMany(
    ['api-timeout', 'max-retries', 'debug-mode'],
    context: ['customer_id' => 'cust-123']
);

// Results are keyed by the requested keys
$timeout = $results['api-timeout']->getValue();
$retries = $results['max-retries']->getValue();
$debug = $results['debug-mode']->getValue();
```

## Return Format

`getMany()` returns an array of Result objects:

```php
$results = $cascade->getMany(['key1', 'key2', 'key3']);

// Each result is a Result object
foreach ($results as $key => $result) {
    if ($result->wasFound()) {
        echo "{$key}: {$result->getValue()} from {$result->getSourceName()}\n";
    } else {
        echo "{$key}: not found\n";
    }
}
```

## Performance Benefits

### Single Source Query

Sources can optimize batch queries:

```php
use Cline\Cascade\Source\CallbackSource;

$optimizedSource = new CallbackSource(
    name: 'database',
    resolver: function(string $key, array $context) {
        // This is called once per key in sequential resolution
        return $this->db->find($key);
    }
);

// Better: Implement a batch-aware source
class BatchDatabaseSource implements SourceInterface
{
    public function getMany(array $keys, array $context): array
    {
        // Single query for all keys
        return $this->db
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key')
            ->toArray();
    }
}
```

## Extracting Values

Extract just the values without Result objects:

```php
$results = $cascade->getMany(['timeout', 'retries', 'max-size']);

// Get array of values
$values = array_map(fn($result) => $result->getValue(), $results);

// Or create a helper
function extractValues(array $results): array
{
    return array_map(fn($r) => $r->getValue(), $results);
}

$values = extractValues($results);
// ['timeout' => 30, 'retries' => 3, 'max-size' => 1024]
```

## With Defaults

Provide defaults for missing keys:

```php
$results = $cascade->getMany([
    'api-timeout',
    'max-retries',
    'debug-mode',
]);

$config = [
    'timeout' => $results['api-timeout']->getValue() ?? 30,
    'retries' => $results['max-retries']->getValue() ?? 3,
    'debug' => $results['debug-mode']->getValue() ?? false,
];
```

## Practical Examples

### Configuration Loading

Load all configuration values at once:

```php
class ConfigLoader
{
    public function __construct(
        private Cascade $cascade,
    ) {}

    public function loadAppConfig(string $customerId): array
    {
        $results = $this->cascade->getMany(
            keys: [
                'api-timeout',
                'max-retries',
                'rate-limit',
                'cache-ttl',
                'log-level',
            ],
            context: ['customer_id' => $customerId],
        );

        return [
            'api' => [
                'timeout' => $results['api-timeout']->getValue() ?? 30,
                'retries' => $results['max-retries']->getValue() ?? 3,
            ],
            'rate_limit' => $results['rate-limit']->getValue() ?? 1000,
            'cache' => [
                'ttl' => $results['cache-ttl']->getValue() ?? 300,
            ],
            'logging' => [
                'level' => $results['log-level']->getValue() ?? 'info',
            ],
        ];
    }
}
```

### Feature Flags

Check multiple feature flags efficiently:

```php
class FeatureFlagChecker
{
    public function __construct(
        private Cascade $cascade,
    ) {}

    public function getFlags(string $userId, array $flags): array
    {
        $results = $this->cascade
            ->using('feature-flags')
            ->getMany($flags, ['user_id' => $userId]);

        $enabled = [];
        foreach ($results as $flag => $result) {
            $enabled[$flag] = (bool) ($result->getValue() ?? false);
        }

        return $enabled;
    }

    public function checkAll(string $userId): array
    {
        return $this->getFlags($userId, [
            'dark-mode',
            'beta-features',
            'new-dashboard',
            'advanced-analytics',
            'api-access',
        ]);
    }
}

// Usage
$flags = $checker->checkAll('user-123');
// [
//     'dark-mode' => true,
//     'beta-features' => false,
//     'new-dashboard' => true,
//     'advanced-analytics' => false,
//     'api-access' => true,
// ]
```

### Credential Bundle

Load all credentials for a service:

```php
class CredentialBundleLoader
{
    public function loadCarrierCredentials(string $carrier, string $customerId): array
    {
        $results = $this->cascade
            ->using('carrier-credentials')
            ->getMany(
                keys: [
                    "{$carrier}.api-key",
                    "{$carrier}.api-secret",
                    "{$carrier}.account-number",
                    "{$carrier}.endpoint-url",
                ],
                context: ['customer_id' => $customerId],
            );

        return [
            'api_key' => $results["{$carrier}.api-key"]->getValue(),
            'api_secret' => $results["{$carrier}.api-secret"]->getValue(),
            'account_number' => $results["{$carrier}.account-number"]->getValue(),
            'endpoint' => $results["{$carrier}.endpoint-url"]->getValue() ?? 'https://api.example.com',
        ];
    }
}
```

### Metrics Collection

Collect metrics about bulk resolution:

```php
class BulkMetricsCollector
{
    public function load(array $keys, array $context = []): array
    {
        $start = microtime(true);

        $results = $this->cascade->getMany($keys, $context);

        $duration = (microtime(true) - $start) * 1000;

        // Track performance
        $this->metrics->histogram('cascade.bulk.duration_ms', $duration);
        $this->metrics->gauge('cascade.bulk.keys_requested', count($keys));

        // Count hits and misses
        $hits = 0;
        $misses = 0;
        $sources = [];

        foreach ($results as $result) {
            if ($result->wasFound()) {
                $hits++;
                $sources[$result->getSourceName()] = ($sources[$result->getSourceName()] ?? 0) + 1;
            } else {
                $misses++;
            }
        }

        $this->metrics->gauge('cascade.bulk.hits', $hits);
        $this->metrics->gauge('cascade.bulk.misses', $misses);

        foreach ($sources as $source => $count) {
            $this->metrics->increment('cascade.bulk.source_hits', ['source' => $source], $count);
        }

        return $results;
    }
}
```

## Different Contexts Per Key

Resolve keys with different contexts using `resolveMany()`:

```php
$results = $cascade->resolveMany([
    ['key' => 'fedex-api-key', 'context' => ['customer_id' => 'cust-123']],
    ['key' => 'ups-api-key', 'context' => ['customer_id' => 'cust-456']],
    ['key' => 'dhl-api-key', 'context' => ['customer_id' => 'cust-789']],
]);

foreach ($results as $result) {
    echo "Key: {$result->key}, Value: {$result->getValue()}\n";
}
```

## Filtering Results

Filter results based on criteria:

```php
$results = $cascade->getMany([
    'feature-a',
    'feature-b',
    'feature-c',
    'feature-d',
]);

// Get only enabled features
$enabled = array_filter(
    $results,
    fn($result) => $result->wasFound() && $result->getValue() === true
);

// Get keys that were found
$found = array_filter(
    $results,
    fn($result) => $result->wasFound()
);

// Get keys that came from specific source
$fromCustomer = array_filter(
    $results,
    fn($result) => $result->getSourceName() === 'customer'
);
```

## Validation

Validate all values after bulk resolution:

```php
class BulkConfigValidator
{
    public function loadAndValidate(array $keys, array $context = []): array
    {
        $results = $this->cascade->getMany($keys, $context);

        $config = [];
        $errors = [];

        foreach ($results as $key => $result) {
            if (!$result->wasFound()) {
                $errors[] = "Missing required config: {$key}";
                continue;
            }

            $value = $result->getValue();

            // Validate based on key
            if (!$this->isValid($key, $value)) {
                $errors[] = "Invalid value for {$key}";
                continue;
            }

            $config[$key] = $value;
        }

        if (!empty($errors)) {
            throw new ConfigValidationException($errors);
        }

        return $config;
    }

    private function isValid(string $key, mixed $value): bool
    {
        return match($key) {
            'api-timeout' => is_int($value) && $value > 0,
            'max-retries' => is_int($value) && $value >= 0,
            'debug-mode' => is_bool($value),
            default => true,
        };
    }
}
```

## Grouping Results

Group results by source:

```php
$results = $cascade->getMany([
    'config-a',
    'config-b',
    'config-c',
    'config-d',
    'config-e',
]);

// Group by source
$bySource = [];
foreach ($results as $key => $result) {
    if ($result->wasFound()) {
        $source = $result->getSourceName();
        $bySource[$source][] = [
            'key' => $key,
            'value' => $result->getValue(),
        ];
    }
}

// Now you have:
// [
//     'customer' => [['key' => 'config-a', 'value' => '...']],
//     'platform' => [['key' => 'config-b', 'value' => '...']],
// ]
```

## Partial Defaults

Provide defaults only for specific keys:

```php
$results = $cascade->getMany([
    'required-key',
    'optional-key',
    'fallback-key',
]);

$defaults = [
    'optional-key' => 'default-optional',
    'fallback-key' => 'default-fallback',
];

$config = [];
foreach ($results as $key => $result) {
    if ($result->wasFound()) {
        $config[$key] = $result->getValue();
    } elseif (isset($defaults[$key])) {
        $config[$key] = $defaults[$key];
    } else {
        throw new MissingRequiredConfigException($key);
    }
}
```

## With Transformers

Transformers apply to bulk resolution:

```php
$cascade = Cascade::from()
    ->fallbackTo($source)
    ->transform(fn($value) => strtoupper($value));

$results = $cascade->getMany(['key1', 'key2', 'key3']);

// All values are transformed
foreach ($results as $key => $result) {
    echo $result->getValue(); // UPPERCASE
}
```

## Logging Bulk Operations

Log bulk resolution for debugging:

```php
class LoggedBulkResolver
{
    public function getMany(array $keys, array $context = []): array
    {
        $this->logger->debug('Bulk resolution started', [
            'keys' => $keys,
            'count' => count($keys),
        ]);

        $results = $this->cascade->getMany($keys, $context);

        $found = 0;
        $missed = 0;

        foreach ($results as $result) {
            $result->wasFound() ? $found++ : $missed++;
        }

        $this->logger->info('Bulk resolution complete', [
            'total' => count($keys),
            'found' => $found,
            'missed' => $missed,
        ]);

        return $results;
    }
}
```

## Best Practices

### 1. Batch Related Keys

```php
// Good: Related configuration in one call
$results = $cascade->getMany([
    'api-timeout',
    'api-retries',
    'api-rate-limit',
]);

// Avoid: Unrelated keys in same batch
$results = $cascade->getMany([
    'api-timeout',
    'user-theme',
    'email-template',
]);
```

### 2. Handle Missing Keys Gracefully

```php
// Good: Check if found
foreach ($results as $key => $result) {
    if ($result->wasFound()) {
        $config[$key] = $result->getValue();
    } else {
        $this->logger->warning("Config not found: {$key}");
        $config[$key] = $this->getDefaultFor($key);
    }
}

// Avoid: Assuming all keys exist
$config = array_map(fn($r) => $r->getValue(), $results); // May have nulls
```

### 3. Use for Configuration Loading

```php
// Good use case: Load related config
public function loadDatabaseConfig(): array
{
    $results = $this->cascade->getMany([
        'db-host',
        'db-port',
        'db-name',
        'db-user',
        'db-password',
    ]);

    return [...];
}
```

## Next Steps

- Use bulk resolution with [Events](#doc-docs-events) for batch monitoring
- Combine with [Transformers](#doc-docs-transformers) for batch processing
- Explore [Advanced Usage](#doc-docs-advanced-usage) for optimization patterns
- See [Cookbook](#doc-docs-cookbook) for real-world bulk resolution examples

<a id="doc-docs-conductors"></a>

Cascade uses the **Conductor Pattern** to provide fluent, chainable APIs for building resolution chains. There are two types of conductors:

## Source Conductor

The `SourceConductor` builds chains of sources with fallback behavior. Start with `Cascade::from()`:

### Basic Chain Building

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\ArraySource;

// Simple inline source
$value = Cascade::from(['api-key' => 'abc123'])
    ->get('api-key'); // 'abc123'

// With fallback sources
$value = Cascade::from(['primary-key' => 'value1'])
    ->fallbackTo(['backup-key' => 'value2'])
    ->get('primary-key'); // 'value1'
```

### Priority-Based Ordering

```php
// Explicit priority (lower = higher priority)
$value = Cascade::from($source1, priority: 10)
    ->fallbackTo($source2, priority: 1)  // Higher priority
    ->fallbackTo($source3, priority: 5)  // Medium priority
    ->get('key'); // Checks $source2 → $source3 → $source1

// Auto-incrementing with fallbackTo
$value = Cascade::from($source1)      // priority: 0
    ->fallbackTo($source2)            // priority: 10
    ->fallbackTo($source3)            // priority: 20
    ->get('key');
```

### Named Resolver Registration

Register source chains as named resolvers for reuse:

```php
use Cline\Cascade\Source\CallbackSource;

// Register the chain
Cascade::from(new CallbackSource(
    name: 'customer-db',
    resolver: fn($key, $ctx) => $db->find($ctx['customer_id'], $key),
))
    ->fallbackTo(new CallbackSource(
        name: 'tenant-db',
        resolver: fn($key, $ctx) => $db->find($ctx['tenant_id'], $key),
    ))
    ->fallbackTo(['platform-defaults' => 'default-value'])
    ->as('credentials');

// Use the named resolver
$apiKey = Cascade::using('credentials')
    ->for(['customer_id' => 123, 'tenant_id' => 456])
    ->get('api-key');
```

### Transformers

Apply transformations to resolved values:

```php
$value = Cascade::from(['name' => 'john'])
    ->transform(fn($v) => strtoupper($v))
    ->get('name'); // 'JOHN'

// Chain multiple transformers
$value = Cascade::from(['price' => '100'])
    ->transform(fn($v) => (int) $v)
    ->transform(fn($v) => $v * 1.1)
    ->get('price'); // 110
```

### Caching

Wrap sources in a PSR-16 cache:

```php
use Psr\SimpleCache\CacheInterface;

$cache = app(CacheInterface::class);

$value = Cascade::from(new ExpensiveApiSource())
    ->cache($cache, ttl: 300)  // Cache for 5 minutes
    ->get('data');

// Custom cache key generator
$value = Cascade::from($source)
    ->cache(
        cache: $cache,
        ttl: 600,
        keyGenerator: fn($key, $ctx) => "custom:{$ctx['tenant']}:{$key}"
    )
    ->get('value');
```

### Direct Resolution

Source conductors can resolve directly without named registration:

```php
// Get with default
$value = Cascade::from($source)->get('key', default: 'fallback');

// Get or throw
$value = Cascade::from($source)->getOrFail('key'); // Throws if not found

// Full result metadata
$result = Cascade::from($source)->resolve('key');
if ($result->wasFound()) {
    $value = $result->getValue();
    $source = $result->getSourceName();
}

// Bulk resolution
$results = Cascade::from($source)->getMany(['key1', 'key2', 'key3']);
```

## Resolution Conductor

The `ResolutionConductor` provides fluent access to named resolvers with context binding. Start with `Cascade::using()`:

### Basic Resolution

```php
// First, define a named resolver
Cascade::defineResolver('config')
    ->source('env', new EnvSource())
    ->source('db', new DatabaseSource());

// Then use it
$value = Cascade::using('config')->get('api-key');
```

### Context Binding

#### Array Context

```php
$value = Cascade::using('credentials')
    ->for(['customer_id' => 123, 'environment' => 'production'])
    ->get('api-key');
```

#### Model Context

Models with `getKey()` are automatically converted to context:

```php
class Customer extends Model {
    public function getKey(): int {
        return $this->id;
    }
}

$customer = Customer::find(123);

// Automatically extracts 'customer_id' => 123
$value = Cascade::using('credentials')
    ->for($customer)
    ->get('api-key');
```

#### Custom Context Extraction

Implement `toCascadeContext()` for custom context:

```php
class Customer extends Model {
    public function toCascadeContext(): array {
        return [
            'customer_id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'environment' => app()->environment(),
        ];
    }
}

$customer = Customer::find(123);

$value = Cascade::using('credentials')
    ->for($customer)
    ->get('api-key');
```

### Transformers

Apply transformations to resolved values:

```php
$value = Cascade::using('config')
    ->transform(fn($v) => json_decode($v, true))
    ->get('settings');

// Chain transformers
$value = Cascade::using('config')
    ->transform(fn($v) => json_decode($v, true))
    ->transform(fn($v) => new SettingsDto($v))
    ->get('settings');
```

### Resolution Methods

```php
// Get with default
$value = Cascade::using('config')->get('key', default: 'fallback');

// Get or throw
$value = Cascade::using('config')->getOrFail('key');

// Full result metadata
$result = Cascade::using('config')->resolve('key');

// Bulk resolution
$results = Cascade::using('config')->getMany(['key1', 'key2']);
```

## Conductor Immutability

Both conductors are **immutable** (or return new instances). Each method returns a new conductor:

```php
$base = Cascade::from($source1);
$withFallback = $base->fallbackTo($source2);  // New instance
$withTransform = $withFallback->transform(fn($v) => $v * 2);  // New instance

// $base remains unchanged
$base->get('key');  // Only queries $source1
```

## Practical Examples

### Multi-Tenant Configuration

```php
// Setup
Cascade::from(new CallbackSource(
    name: 'customer',
    resolver: fn($k, $ctx) => DB::table('customer_config')
        ->where('customer_id', $ctx['customer_id'])
        ->value($k),
))
    ->fallbackTo(new CallbackSource(
        name: 'tenant',
        resolver: fn($k, $ctx) => DB::table('tenant_config')
            ->where('tenant_id', $ctx['tenant_id'])
            ->value($k),
    ))
    ->fallbackTo(['app-defaults' => 'default-value'])
    ->as('config');

// Usage
$customer = Customer::find(123);

$theme = Cascade::using('config')
    ->for($customer)  // Extracts customer_id and tenant_id
    ->get('theme');  // Customer → Tenant → Default
```

### Feature Flags

```php
// Setup with caching
Cascade::from(new CallbackSource(
    name: 'user-flags',
    resolver: fn($k, $ctx) => $this->flags->forUser($ctx['user_id'], $k),
))
    ->cache($cache, ttl: 60)
    ->fallbackTo(new CallbackSource(
        name: 'global-flags',
        resolver: fn($k) => $this->flags->global($k),
    ))
    ->as('features');

// Usage
$user = auth()->user();

$enabled = Cascade::using('features')
    ->for($user)
    ->transform(fn($v) => (bool) $v)
    ->get('new-dashboard');
```

### API Credentials

```php
// Setup
Cascade::from(new CallbackSource(
    name: 'customer-credentials',
    resolver: fn($k, $ctx) => Crypt::decrypt(
        DB::table('customer_credentials')
            ->where('customer_id', $ctx['customer_id'])
            ->value($k)
    ),
    supports: fn($k, $ctx) => isset($ctx['customer_id']),
))
    ->fallbackTo(config('services'))
    ->as('api-credentials');

// Usage with model
$customer = Customer::find(123);

$apiKey = Cascade::using('api-credentials')
    ->for($customer)
    ->getOrFail('stripe.secret_key');  // Throws if not found
```

## Advanced Patterns

### Conditional Sources

```php
Cascade::from(new CallbackSource(
    name: 'production-only',
    resolver: fn($k) => $this->productionConfig($k),
    supports: fn($k, $ctx) => $ctx['environment'] === 'production',
))
    ->fallbackTo(new CallbackSource(
        name: 'development',
        resolver: fn($k) => $this->devConfig($k),
    ))
    ->as('environment-config');

$value = Cascade::using('environment-config')
    ->for(['environment' => app()->environment()])
    ->get('api-endpoint');
```

### Nested Conductors

```php
// Build sub-chains
$primaryChain = Cascade::from($fastSource)
    ->fallbackTo($slowSource)
    ->as('primary');

$backupChain = Cascade::from($backupSource1)
    ->fallbackTo($backupSource2)
    ->as('backup');

// Combine them
$value = Cascade::using('primary')
    ->get('key') ?? Cascade::using('backup')->get('key');
```

## Next Steps

- [Sources](#doc-docs-sources) - Understand built-in source types
- [Events](#doc-docs-events) - Monitor resolution lifecycle
- [Cookbook](#doc-docs-cookbook) - Real-world patterns and recipes

<a id="doc-docs-cookbook"></a>

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

- Review [Basic Usage](#doc-docs-basic-usage) for fundamentals
- Explore [Conductors](#doc-docs-conductors) for fluent API patterns
- Use [Events](#doc-docs-events) to monitor your implementations
- Check [Advanced Usage](#doc-docs-advanced-usage) for optimization patterns

<a id="doc-docs-events"></a>

Cascade provides event listeners to monitor the resolution lifecycle. Use events for logging, metrics, debugging, and performance monitoring.

## Available Events

### SourceQueried

Fired when a source is queried during resolution.

```php
use Cline\Cascade\Event\SourceQueried;

$cascade->onSourceQueried(function(SourceQueried $event) {
    // $event->sourceName - Name of the source being queried
    // $event->key - The key being resolved
    // $event->context - Resolution context
    // $event->timestamp - When the query started
});
```

### ValueResolved

Fired when a value is successfully found.

```php
use Cline\Cascade\Event\ValueResolved;

$cascade->onResolved(function(ValueResolved $event) {
    // $event->key - The key that was resolved
    // $event->value - The resolved value
    // $event->sourceName - Source that provided the value
    // $event->durationMs - Time taken to resolve (milliseconds)
    // $event->attemptedSources - All sources attempted
    // $event->context - Resolution context
});
```

### ResolutionFailed

Fired when no source could provide a value.

```php
use Cline\Cascade\Event\ResolutionFailed;

$cascade->onFailed(function(ResolutionFailed $event) {
    // $event->key - The key that failed to resolve
    // $event->attemptedSources - All sources that were tried
    // $event->context - Resolution context
    // $event->timestamp - When resolution failed
});
```

## Basic Usage

### Logging

Log resolution activity:

```php
use Cline\Cascade\Cascade;
use Psr\Log\LoggerInterface;

class LoggedCascade
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function create(): Cascade
    {
        $cascade = Cascade::from()
            ->fallbackTo($this->customerSource)
            ->fallbackTo($this->platformSource);

        // Log source queries
        $cascade->onSourceQueried(function($event) {
            $this->logger->debug('Querying source', [
                'source' => $event->sourceName,
                'key' => $event->key,
                'context' => $event->context,
            ]);
        });

        // Log successful resolutions
        $cascade->onResolved(function($event) {
            $this->logger->info('Value resolved', [
                'key' => $event->key,
                'source' => $event->sourceName,
                'duration_ms' => $event->durationMs,
                'attempted' => count($event->attemptedSources),
            ]);
        });

        // Log failures
        $cascade->onFailed(function($event) {
            $this->logger->warning('Resolution failed', [
                'key' => $event->key,
                'attempted_sources' => $event->attemptedSources,
            ]);
        });

        return $cascade;
    }
}
```

### Metrics Collection

Track resolution metrics:

```php
use Cline\Cascade\Cascade;

class MetricsCascade
{
    public function __construct(
        private MetricsCollector $metrics,
    ) {}

    public function create(): Cascade
    {
        $cascade = Cascade::from()
            ->fallbackTo($this->dbSource)
            ->fallbackTo($this->cacheSource);

        $cascade->onResolved(function($event) {
            // Track resolution time
            $this->metrics->histogram('cascade.resolution.duration', $event->durationMs, [
                'source' => $event->sourceName,
            ]);

            // Count successes by source
            $this->metrics->increment('cascade.resolution.success', [
                'source' => $event->sourceName,
                'key' => $event->key,
            ]);

            // Track number of sources attempted
            $this->metrics->gauge('cascade.resolution.attempts',
                count($event->attemptedSources)
            );
        });

        $cascade->onFailed(function($event) {
            // Count failures
            $this->metrics->increment('cascade.resolution.failed', [
                'key' => $event->key,
            ]);
        });

        return $cascade;
    }
}
```

## Performance Monitoring

### Slow Query Detection

Alert on slow resolutions:

```php
$cascade->onResolved(function(ValueResolved $event) {
    if ($event->durationMs > 100) {
        $this->alerts->slow('Slow cascade resolution', [
            'key' => $event->key,
            'source' => $event->sourceName,
            'duration_ms' => $event->durationMs,
            'threshold_ms' => 100,
        ]);
    }
});
```

### Source Performance Tracking

Track which sources are slowest:

```php
class SourcePerformanceMonitor
{
    private array $stats = [];

    public function monitor(Cascade $cascade): void
    {
        $cascade->onResolved(function(ValueResolved $event) {
            $source = $event->sourceName;

            if (!isset($this->stats[$source])) {
                $this->stats[$source] = [
                    'count' => 0,
                    'total_ms' => 0,
                    'min_ms' => PHP_FLOAT_MAX,
                    'max_ms' => 0,
                ];
            }

            $this->stats[$source]['count']++;
            $this->stats[$source]['total_ms'] += $event->durationMs;
            $this->stats[$source]['min_ms'] = min(
                $this->stats[$source]['min_ms'],
                $event->durationMs
            );
            $this->stats[$source]['max_ms'] = max(
                $this->stats[$source]['max_ms'],
                $event->durationMs
            );
        });
    }

    public function getStats(): array
    {
        return array_map(function($stats) {
            return [
                'count' => $stats['count'],
                'avg_ms' => $stats['total_ms'] / $stats['count'],
                'min_ms' => $stats['min_ms'],
                'max_ms' => $stats['max_ms'],
            ];
        }, $this->stats);
    }
}
```

## Debugging

### Resolution Trace

Trace the resolution process:

```php
class ResolutionTracer
{
    private array $traces = [];

    public function trace(Cascade $cascade): void
    {
        $traceId = uniqid('trace_');

        $cascade->onSourceQueried(function($event) use ($traceId) {
            $this->traces[$traceId][] = [
                'type' => 'query',
                'source' => $event->sourceName,
                'key' => $event->key,
                'timestamp' => $event->timestamp,
            ];
        });

        $cascade->onResolved(function($event) use ($traceId) {
            $this->traces[$traceId][] = [
                'type' => 'resolved',
                'source' => $event->sourceName,
                'key' => $event->key,
                'duration_ms' => $event->durationMs,
            ];
        });

        $cascade->onFailed(function($event) use ($traceId) {
            $this->traces[$traceId][] = [
                'type' => 'failed',
                'key' => $event->key,
                'attempted' => $event->attemptedSources,
            ];
        });
    }

    public function getTrace(string $traceId): array
    {
        return $this->traces[$traceId] ?? [];
    }
}
```

### Debug Mode

Enable verbose debugging in development:

```php
if (app()->environment('local')) {
    $cascade->onSourceQueried(function($event) {
        dump([
            'action' => 'query',
            'source' => $event->sourceName,
            'key' => $event->key,
            'context' => $event->context,
        ]);
    });

    $cascade->onResolved(function($event) {
        dump([
            'action' => 'resolved',
            'key' => $event->key,
            'source' => $event->sourceName,
            'value' => $event->value,
            'duration_ms' => $event->durationMs,
        ]);
    });
}
```

## Audit Logging

### Security Auditing

Track access to sensitive values:

```php
class SecurityAuditor
{
    public function __construct(
        private AuditLogger $audit,
    ) {}

    public function monitor(Cascade $cascade): void
    {
        $cascade->onResolved(function(ValueResolved $event) {
            // Only audit sensitive keys
            if ($this->isSensitive($event->key)) {
                $this->audit->log([
                    'event' => 'credential_accessed',
                    'key' => $event->key,
                    'source' => $event->sourceName,
                    'context' => $event->context,
                    'user_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'timestamp' => now(),
                ]);
            }
        });
    }

    private function isSensitive(string $key): bool
    {
        return str_contains($key, 'api-key')
            || str_contains($key, 'secret')
            || str_contains($key, 'password');
    }
}
```

### Compliance Logging

Track data access for compliance:

```php
$cascade->onResolved(function(ValueResolved $event) {
    if (isset($event->context['customer_id'])) {
        $this->compliance->log([
            'event_type' => 'data_access',
            'customer_id' => $event->context['customer_id'],
            'data_key' => $event->key,
            'accessed_by' => auth()->id(),
            'source' => $event->sourceName,
            'timestamp' => now(),
        ]);
    }
});
```

## Cache Statistics

### Cache Hit Rate

Track cache effectiveness:

```php
class CacheStatsCollector
{
    private int $hits = 0;
    private int $misses = 0;

    public function monitor(Cascade $cascade): void
    {
        $cascade->onResolved(function(ValueResolved $event) {
            if ($event->sourceName === 'cache') {
                $this->hits++;
            } else {
                $this->misses++;
            }
        });
    }

    public function getHitRate(): float
    {
        $total = $this->hits + $this->misses;
        return $total > 0 ? ($this->hits / $total) * 100 : 0;
    }

    public function getStats(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => $this->getHitRate(),
        ];
    }
}
```

## Error Alerting

### Failed Resolution Alerts

Alert when critical keys fail to resolve:

```php
$cascade->onFailed(function(ResolutionFailed $event) {
    // Alert on critical key failures
    if ($this->isCritical($event->key)) {
        $this->alerts->critical('Critical configuration missing', [
            'key' => $event->key,
            'attempted_sources' => $event->attemptedSources,
            'context' => $event->context,
        ]);
    }
});

private function isCritical(string $key): bool
{
    return in_array($key, [
        'database-password',
        'api-key',
        'encryption-key',
    ]);
}
```

### Source Failure Detection

Detect when sources consistently fail:

```php
class SourceHealthMonitor
{
    private array $failures = [];

    public function monitor(Cascade $cascade): void
    {
        $cascade->onSourceQueried(function($event) {
            $this->failures[$event->sourceName] = 0;
        });

        $cascade->onFailed(function($event) {
            foreach ($event->attemptedSources as $source) {
                $this->failures[$source] = ($this->failures[$source] ?? 0) + 1;

                // Alert if source fails 10 times in a row
                if ($this->failures[$source] >= 10) {
                    $this->alerts->warning("Source consistently failing", [
                        'source' => $source,
                        'consecutive_failures' => $this->failures[$source],
                    ]);
                }
            }
        });
    }
}
```

## Multiple Listeners

Register multiple listeners for the same event:

```php
$cascade = Cascade::from()
    ->fallbackTo($source)
    // First listener: logging
    ->onResolved(function($event) {
        $this->logger->info('Resolved', ['key' => $event->key]);
    })
    // Second listener: metrics
    ->onResolved(function($event) {
        $this->metrics->increment('resolutions');
    })
    // Third listener: audit
    ->onResolved(function($event) {
        $this->audit->log($event);
    });
```

## Event Data Reference

### SourceQueried Properties

```php
class SourceQueried
{
    public string $sourceName;      // Name of source being queried
    public string $key;             // Key being resolved
    public array $context;          // Resolution context
    public float $timestamp;        // Unix timestamp when query started
}
```

### ValueResolved Properties

```php
class ValueResolved
{
    public string $key;             // Key that was resolved
    public mixed $value;            // The resolved value
    public string $sourceName;      // Source that provided value
    public float $durationMs;       // Resolution time in milliseconds
    public array $attemptedSources; // All sources attempted
    public array $context;          // Resolution context
}
```

### ResolutionFailed Properties

```php
class ResolutionFailed
{
    public string $key;             // Key that failed to resolve
    public array $attemptedSources; // All sources attempted
    public array $context;          // Resolution context
    public float $timestamp;        // Unix timestamp of failure
}
```

## Best Practices

### 1. Keep Listeners Lightweight

```php
// Good: Quick logging
$cascade->onResolved(function($event) {
    $this->logger->info('Resolved', ['key' => $event->key]);
});

// Avoid: Heavy processing
$cascade->onResolved(function($event) {
    $this->sendEmail($event); // Too slow for sync processing
    $this->updateDatabase($event); // Use queued jobs instead
});
```

### 2. Use Events for Observability

```php
// Good: Non-intrusive monitoring
$cascade->onResolved(fn($e) => $this->metrics->increment('resolutions'));

// Good: Debugging aid
$cascade->onFailed(fn($e) => $this->logger->warning('Failed', ['key' => $e->key]));
```

### 3. Protect Against Exceptions

```php
$cascade->onResolved(function($event) {
    try {
        $this->metrics->record($event);
    } catch (\Throwable $e) {
        // Don't let listener errors break resolution
        $this->logger->error('Metrics failed', ['error' => $e->getMessage()]);
    }
});
```

## Next Steps

- Use events with [Bulk Resolution](#doc-docs-bulk-resolution) for batch monitoring
- Combine events with [Result Metadata](#doc-docs-result-metadata) for detailed tracking
- Explore [Advanced Usage](#doc-docs-advanced-usage) for complex event patterns
- See [Cookbook](#doc-docs-cookbook) for real-world event handling examples

<a id="doc-docs-named-resolvers"></a>

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

- Learn about [Result Metadata](#doc-docs-result-metadata) to track which source provided values
- Use [Transformers](#doc-docs-transformers) to modify resolved values per resolver
- Explore [Repositories](#doc-docs-repositories) to load resolver definitions from config files
- Set up [Events](#doc-docs-events) for monitoring resolution across resolvers

<a id="doc-docs-repositories"></a>

Repositories provide a way to store and load resolver configurations from external sources instead of defining them in code. This enables dynamic resolver management and configuration-driven resolution chains.

## Repository Interface

All repositories implement `ResolverRepositoryInterface`:

```php
interface ResolverRepositoryInterface
{
    /** Get a resolver definition by name */
    public function get(string $name): array;

    /** Check if a resolver definition exists */
    public function has(string $name): bool;

    /** Get all resolver definitions */
    public function all(): array;

    /** Get multiple resolver definitions */
    public function getMany(array $names): array;
}
```

## ArrayRepository

In-memory resolver definitions, useful for testing or config-driven setups:

```php
use Cline\Cascade\Repository\ArrayRepository;

$repository = new ArrayRepository([
    'carrier-credentials' => [
        'description' => 'Resolve carrier API credentials',
        'sources' => [
            ['name' => 'customer', 'type' => 'callback', 'priority' => 1],
            ['name' => 'platform', 'type' => 'callback', 'priority' => 2],
        ],
    ],
    'feature-flags' => [
        'description' => 'Resolve feature flags with fallback',
        'sources' => [
            ['name' => 'user', 'type' => 'callback', 'priority' => 1],
            ['name' => 'organization', 'type' => 'callback', 'priority' => 2],
            ['name' => 'global', 'type' => 'array', 'priority' => 3],
        ],
    ],
]);

// Get a single definition
$definition = $repository->get('carrier-credentials');

// Check existence
if ($repository->has('feature-flags')) {
    $flags = $repository->get('feature-flags');
}

// Get all definitions
$all = $repository->all();
```

## JsonRepository

Load resolver definitions from JSON files:

### Single File

```php
use Cline\Cascade\Repository\JsonRepository;

$repository = new JsonRepository('/etc/cascade/resolvers.json');
```

### Multiple Files

Files are merged, with later files taking precedence:

```php
$repository = new JsonRepository([
    '/etc/cascade/resolvers.json',      // Base definitions
    '/app/config/resolvers.json',       // Application overrides
]);
```

### With Base Path

```php
$repository = new JsonRepository(
    paths: ['resolvers.json', 'custom-resolvers.json'],
    basePath: '/etc/cascade',
);
// Loads: /etc/cascade/resolvers.json and /etc/cascade/custom-resolvers.json
```

### JSON Format

```json
{
    "carrier-credentials": {
        "description": "Resolve carrier API credentials",
        "sources": [
            {
                "name": "customer",
                "type": "callback",
                "priority": 1
            },
            {
                "name": "platform",
                "type": "callback",
                "priority": 2
            }
        ]
    },
    "feature-flags": {
        "description": "Resolve feature flags with fallback",
        "sources": [
            {
                "name": "user",
                "type": "callback",
                "priority": 1
            },
            {
                "name": "organization",
                "type": "callback",
                "priority": 2
            },
            {
                "name": "global",
                "type": "array",
                "priority": 3,
                "values": {
                    "dark-mode": true,
                    "beta-features": false
                }
            }
        ]
    }
}
```

## YamlRepository

Load resolver definitions from YAML files (requires `symfony/yaml`):

### Installation

```bash
composer require symfony/yaml
```

### Usage

```php
use Cline\Cascade\Repository\YamlRepository;

// Single file
$repository = new YamlRepository('/etc/cascade/resolvers.yaml');

// Multiple files
$repository = new YamlRepository([
    '/etc/cascade/resolvers.yaml',
    '/app/config/resolvers.yaml',
]);

// With base path
$repository = new YamlRepository(
    paths: ['base.yaml', 'overrides.yaml'],
    basePath: '/etc/cascade',
);
```

### YAML Format

```yaml
carrier-credentials:
  description: Resolve carrier API credentials
  sources:
    - name: customer
      type: callback
      priority: 1
    - name: platform
      type: callback
      priority: 2

feature-flags:
  description: Resolve feature flags with fallback
  sources:
    - name: user
      type: callback
      priority: 1
    - name: organization
      type: callback
      priority: 2
    - name: global
      type: array
      priority: 3
      values:
        dark-mode: true
        beta-features: false
```

## DatabaseRepository

Load resolver definitions from a database table:

### Basic Usage

```php
use Cline\Cascade\Repository\DatabaseRepository;

$repository = new DatabaseRepository(
    pdo: $pdo,
    table: 'resolvers',
    nameColumn: 'name',
    definitionColumn: 'definition', // JSON column
);
```

### With Conditions

Filter which resolvers are loaded:

```php
$repository = new DatabaseRepository(
    pdo: $pdo,
    table: 'resolvers',
    conditions: ['is_active' => true, 'environment' => 'production'],
);
```

### Database Schema

```sql
CREATE TABLE resolvers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    definition JSONB NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    environment VARCHAR(50) DEFAULT 'production',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_resolvers_name ON resolvers(name);
CREATE INDEX idx_resolvers_active ON resolvers(is_active) WHERE is_active = TRUE;
CREATE INDEX idx_resolvers_env ON resolvers(environment);
```

### Example Row

```sql
INSERT INTO resolvers (name, description, definition, is_active) VALUES (
    'carrier-credentials',
    'Resolve carrier API credentials',
    '{
        "sources": [
            {"name": "customer", "type": "callback", "priority": 1},
            {"name": "platform", "type": "callback", "priority": 2}
        ]
    }'::jsonb,
    true
);
```

## ChainedRepository

Try multiple repositories in order (first match wins):

```php
use Cline\Cascade\Repository\{ChainedRepository, JsonRepository, DatabaseRepository};

$repository = new ChainedRepository([
    new JsonRepository('/app/config/resolver-overrides.json'),  // 1. Local overrides
    new DatabaseRepository($pdo, 'resolvers'),                  // 2. Database storage
    new JsonRepository('/etc/cascade/resolvers.json'),          // 3. System defaults
]);

// Searches repositories in order, returns first match
$definition = $repository->get('carrier-credentials');
```

### Use Cases

#### Development Overrides

```php
$repository = new ChainedRepository([
    new JsonRepository('/app/local-overrides.json'),  // Developer overrides
    new DatabaseRepository($pdo, 'resolvers'),        // Shared config
]);
```

#### Environment-Specific Configuration

```php
$repository = new ChainedRepository([
    new JsonRepository("/etc/cascade/{$env}.json"),   // Environment-specific
    new JsonRepository('/etc/cascade/base.json'),     // Base config
]);
```

## CachedRepository

Wrap any repository with PSR-16 caching:

```php
use Cline\Cascade\Repository\{CachedRepository, DatabaseRepository};

$repository = new CachedRepository(
    inner: new DatabaseRepository($pdo, 'resolvers'),
    cache: $cache, // PSR-16 CacheInterface
    ttl: 300,      // 5 minutes
    prefix: 'cascade:resolvers:',
);

// First call hits database
$definition = $repository->get('carrier-credentials');

// Subsequent calls hit cache
$definition = $repository->get('carrier-credentials'); // From cache

// Invalidate cache
$repository->forget('carrier-credentials');

// Clear all cached definitions
$repository->flush();
```

### Cache Key Generation

```php
$repository = new CachedRepository(
    inner: $dbRepository,
    cache: $cache,
    ttl: 300,
    keyGenerator: fn($name) => "app:cascade:resolver:{$name}",
);
```

## Using Repositories with Cascade

### Basic Setup

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Repository\YamlRepository;

// Create cascade with repository
$repository = new YamlRepository('/etc/cascade/resolvers.yaml');
$cascade = Cascade::withRepository($repository);

// Sources still need to be bound at runtime
$cascade->bindSource('customer', new CallbackSource(
    name: 'customer',
    resolver: fn($key, $ctx) => $customerCreds->find($ctx['customer_id'], $key),
    supports: fn($key, $ctx) => isset($ctx['customer_id']),
));

$cascade->bindSource('platform', new CallbackSource(
    name: 'platform',
    resolver: fn($key, $ctx) => $platformCreds->find($key),
));

// Use resolver defined in YAML
$credentials = $cascade->using('carrier-credentials')
    ->get('fedex', ['customer_id' => 'cust-123']);
```

### Complete Example

```php
class CredentialService
{
    private Cascade $cascade;

    public function __construct(
        private CustomerRepository $customers,
        private PlatformRepository $platform,
        private PDO $pdo,
        private CacheInterface $cache,
    ) {
        $this->cascade = $this->buildCascade();
    }

    private function buildCascade(): Cascade
    {
        // Load resolver definitions from cached database
        $repository = new CachedRepository(
            inner: new ChainedRepository([
                new JsonRepository('/app/config/resolvers.json'),      // Local overrides
                new DatabaseRepository($this->pdo, 'resolvers'),       // Database config
                new JsonRepository('/etc/cascade/resolvers.json'),     // System defaults
            ]),
            cache: $this->cache,
            ttl: 600,
        );

        $cascade = Cascade::withRepository($repository);

        // Bind actual source implementations
        $cascade->bindSource('customer', new CallbackSource(
            name: 'customer',
            resolver: fn($carrier, $ctx) =>
                $this->customers->getCarrierCredentials($ctx['customer_id'], $carrier),
            supports: fn($carrier, $ctx) => isset($ctx['customer_id']),
        ));

        $cascade->bindSource('platform', new CallbackSource(
            name: 'platform',
            resolver: fn($carrier) =>
                $this->platform->getCarrierCredentials($carrier),
        ));

        return $cascade;
    }

    public function getCredentials(string $carrier, string $customerId): array
    {
        return $this->cascade
            ->using('carrier-credentials')
            ->getOrFail($carrier, ['customer_id' => $customerId]);
    }
}
```

## Dynamic Resolver Management

### Adding Resolvers at Runtime

```php
class DynamicResolverManager
{
    public function __construct(
        private DatabaseRepository $repository,
        private PDO $pdo,
    ) {}

    public function createResolver(string $name, array $definition): void
    {
        $this->pdo->prepare(
            'INSERT INTO resolvers (name, definition) VALUES (?, ?)'
        )->execute([
            $name,
            json_encode($definition),
        ]);
    }

    public function updateResolver(string $name, array $definition): void
    {
        $this->pdo->prepare(
            'UPDATE resolvers SET definition = ?, updated_at = NOW() WHERE name = ?'
        )->execute([
            json_encode($definition),
            $name,
        ]);
    }

    public function deleteResolver(string $name): void
    {
        $this->pdo->prepare(
            'DELETE FROM resolvers WHERE name = ?'
        )->execute([$name]);
    }
}
```

### A/B Testing Resolvers

```php
class ABTestingRepository implements ResolverRepositoryInterface
{
    public function __construct(
        private DatabaseRepository $database,
        private string $variant,
    ) {}

    public function get(string $name): array
    {
        // Try variant-specific resolver first
        $variantName = "{$name}-{$this->variant}";

        if ($this->database->has($variantName)) {
            return $this->database->get($variantName);
        }

        // Fall back to default
        return $this->database->get($name);
    }

    // ... implement other methods
}

// Usage
$variant = $this->abTest->getVariant($userId);
$repository = new ABTestingRepository($dbRepository, $variant);
```

## Repository Best Practices

### 1. Use Caching for Production

```php
// Good: Cache database lookups
$repository = new CachedRepository(
    inner: new DatabaseRepository($pdo, 'resolvers'),
    cache: $cache,
    ttl: 300,
);

// Development: Skip cache for immediate updates
if (app()->environment('local')) {
    $repository = new DatabaseRepository($pdo, 'resolvers');
}
```

### 2. Chain for Flexibility

```php
// Good: Allow overrides at multiple levels
$repository = new ChainedRepository([
    new JsonRepository('/app/local-overrides.json'),    // Developer
    new JsonRepository('/app/config/overrides.json'),   // Application
    new DatabaseRepository($pdo, 'resolvers'),          // Database
    new JsonRepository('/etc/cascade/defaults.json'),   // System
]);
```

### 3. Validate Definitions

```php
class ValidatingRepository implements ResolverRepositoryInterface
{
    public function __construct(
        private ResolverRepositoryInterface $inner,
    ) {}

    public function get(string $name): array
    {
        $definition = $this->inner->get($name);

        if (!$this->isValid($definition)) {
            throw new InvalidResolverDefinitionException($name);
        }

        return $definition;
    }

    private function isValid(array $definition): bool
    {
        return isset($definition['sources'])
            && is_array($definition['sources'])
            && !empty($definition['sources']);
    }
}
```

## Migration from Code to Repository

### Before (Code-Based)

```php
$cascade = new Cascade();

$cascade->defineResolver('credentials')
    ->source('customer', $customerSource, priority: 1)
    ->source('platform', $platformSource, priority: 2);
```

### After (Repository-Based)

**resolvers.json:**
```json
{
    "credentials": {
        "sources": [
            {"name": "customer", "type": "callback", "priority": 1},
            {"name": "platform", "type": "callback", "priority": 2}
        ]
    }
}
```

**Code:**
```php
$repository = new JsonRepository('/etc/cascade/resolvers.json');
$cascade = Cascade::withRepository($repository);

$cascade->bindSource('customer', $customerSource);
$cascade->bindSource('platform', $platformSource);
```

## Next Steps

- Combine repositories with [Named Resolvers](#doc-docs-named-resolvers) for dynamic management
- Use [Events](#doc-docs-events) to monitor repository-loaded resolvers
- Explore [Advanced Usage](#doc-docs-advanced-usage) for complex repository patterns
- See [Cookbook](#doc-docs-cookbook) for real-world repository examples

<a id="doc-docs-result-metadata"></a>

Result objects provide detailed metadata about the resolution process, including which source provided the value and which sources were attempted.

## The Result Object

When using `resolve()` instead of `get()`, you receive a `Result` object:

```php
use Cline\Cascade\Result;

$result = $cascade->resolve('api-key', ['customer_id' => 'cust-123']);

$result->getValue();           // The resolved value
$result->wasFound();           // true if value was found
$result->getSource();          // SourceInterface that provided the value
$result->getSourceName();      // Name of the source
$result->getAttemptedSources(); // Array of all source names tried
$result->getMetadata();        // Additional source-specific metadata
```

## Basic Usage

### Getting the Value

```php
$result = $cascade->resolve('api-timeout');

if ($result->wasFound()) {
    $timeout = $result->getValue();
    echo "Using timeout: {$timeout}";
} else {
    echo "No timeout configured";
}
```

### Checking Success

```php
$result = $cascade->resolve('api-key', ['customer_id' => 'cust-123']);

if ($result->wasFound()) {
    $this->logger->info('API key resolved', [
        'source' => $result->getSourceName(),
    ]);
} else {
    $this->logger->warning('API key not found', [
        'attempted' => $result->getAttemptedSources(),
    ]);
}
```

## Source Information

### Identifying the Source

Track which source provided the value:

```php
$result = $cascade->resolve('fedex-api-key', ['customer_id' => 'cust-123']);

$sourceName = $result->getSourceName();

match($sourceName) {
    'customer' => $this->metrics->increment('customer_credentials_used'),
    'platform' => $this->metrics->increment('platform_credentials_used'),
    default => null,
};
```

### Source Object

Access the source object directly:

```php
$result = $cascade->resolve('api-key');

if ($result->wasFound()) {
    $source = $result->getSource();
    $metadata = $source->getMetadata();

    $this->logger->debug('Value resolved', [
        'source' => $source->getName(),
        'source_type' => $metadata['type'] ?? 'unknown',
    ]);
}
```

## Attempted Sources

See which sources were queried during resolution:

```php
$result = $cascade->resolve('api-key', ['customer_id' => 'cust-123']);

$attempted = $result->getAttemptedSources();
// ['customer', 'platform']

$this->logger->debug('Resolution complete', [
    'found' => $result->wasFound(),
    'source' => $result->getSourceName(),
    'attempted_count' => count($attempted),
    'attempted_sources' => $attempted,
]);
```

### Understanding Resolution Order

```php
$cascade = Cascade::from()
    ->fallbackTo($customerSource, priority: 1)   // Tried first
    ->fallbackTo($tenantSource, priority: 2)     // Tried second
    ->fallbackTo($defaultSource, priority: 3);   // Tried third

$result = $cascade->resolve('timeout', ['customer_id' => 'cust-123']);

// If found in tenant source:
$result->getAttemptedSources(); // ['customer', 'tenant']
// Customer was tried first (no value), tenant was tried second (found)
// Default source was never queried
```

## Source-Specific Metadata

Sources can provide additional metadata about the resolved value:

```php
use Cline\Cascade\Source\CallbackSource;

$source = new CallbackSource(
    name: 'database',
    resolver: function($key) {
        $row = $this->db->find($key);
        return $row?->value;
    },
);

// Access metadata
$result = $cascade->resolve('api-key');
$metadata = $result->getMetadata();

// Metadata might include:
// - Cache status
// - Query execution time
// - Database server used
// - Timestamp of last update
```

### Custom Metadata Example

```php
class TimedCallbackSource extends CallbackSource
{
    public function get(string $key, array $context): mixed
    {
        $start = microtime(true);
        $value = parent::get($key, $context);
        $duration = microtime(true) - $start;

        return $value; // Store duration in metadata
    }

    public function getMetadata(): array
    {
        return [
            'type' => 'timed-callback',
            'last_duration_ms' => $this->lastDuration * 1000,
        ];
    }
}
```

## Practical Examples

### Billing Based on Source

Charge customers only when using platform credentials:

```php
class ShippingService
{
    public function createShipment(Order $order): Shipment
    {
        $result = $this->cascade
            ->using('carrier-credentials')
            ->resolve('fedex', ['customer_id' => $order->customerId]);

        // Create shipment with credentials
        $shipment = $this->fedex->ship($order, $result->getValue());

        // Track which credentials were used
        if ($result->getSourceName() === 'platform') {
            // Customer used platform credentials - billable event
            $this->billing->recordUsage([
                'customer_id' => $order->customerId,
                'carrier' => 'fedex',
                'billable' => true,
                'rate' => 0.25, // $0.25 per shipment
            ]);
        }

        return $shipment;
    }
}
```

### Audit Logging

Log which source provided sensitive values:

```php
class AuditLogger
{
    public function logCredentialAccess(string $key, string $userId, Result $result): void
    {
        $this->audit->log([
            'event' => 'credential_accessed',
            'user_id' => $userId,
            'credential_key' => $key,
            'found' => $result->wasFound(),
            'source' => $result->getSourceName(),
            'attempted_sources' => $result->getAttemptedSources(),
            'timestamp' => now(),
        ]);
    }
}

// Usage
$result = $cascade->resolve('admin-password', ['user_id' => $userId]);

$this->auditLogger->logCredentialAccess('admin-password', $userId, $result);

if ($result->wasFound()) {
    $password = $result->getValue();
    // Use password...
}
```

### Performance Monitoring

Track resolution performance:

```php
class MetricsCollector
{
    public function recordResolution(string $key, Result $result): void
    {
        $this->metrics->histogram('cascade.resolution.sources_attempted', [
            'count' => count($result->getAttemptedSources()),
        ]);

        $this->metrics->increment('cascade.resolution.total');

        if ($result->wasFound()) {
            $this->metrics->increment('cascade.resolution.success', [
                'source' => $result->getSourceName(),
            ]);
        } else {
            $this->metrics->increment('cascade.resolution.miss', [
                'key' => $key,
            ]);
        }
    }
}
```

### Fallback Notifications

Alert when falling back to default values:

```php
class ConfigService
{
    public function getConfig(string $key, array $context = []): mixed
    {
        $result = $this->cascade->resolve($key, $context);

        // Alert if using fallback
        if ($result->wasFound() && $result->getSourceName() === 'defaults') {
            $this->alerts->warn("Using default value for {$key}", [
                'attempted' => $result->getAttemptedSources(),
                'context' => $context,
            ]);
        }

        return $result->getValue();
    }
}
```

### Debug Information

Provide detailed debug information in development:

```php
class DebugResolver
{
    public function resolve(string $key, array $context = []): array
    {
        $result = $this->cascade->resolve($key, $context);

        if (app()->isDebug()) {
            return [
                'value' => $result->getValue(),
                'debug' => [
                    'found' => $result->wasFound(),
                    'source' => $result->getSourceName(),
                    'attempted' => $result->getAttemptedSources(),
                    'metadata' => $result->getMetadata(),
                ],
            ];
        }

        return ['value' => $result->getValue()];
    }
}
```

## Result Factory Methods

Create Result objects manually for testing:

```php
use Cline\Cascade\Result;

// Found result
$result = Result::found(
    value: 'api-key-value',
    source: $customerSource,
    attempted: ['customer'],
    metadata: ['cached' => false],
);

// Not found result
$result = Result::notFound(
    attempted: ['customer', 'platform', 'defaults'],
);
```

## Comparing get() vs resolve()

```php
// get() - Returns the value directly (or null/default)
$value = $cascade->get('api-key', ['customer_id' => 'cust-123']);
// Type: mixed (the actual value or null)

// resolve() - Returns Result object with metadata
$result = $cascade->resolve('api-key', ['customer_id' => 'cust-123']);
// Type: Result
$value = $result->getValue();

// When to use get():
// - You only need the value
// - You don't care which source provided it
// - Simple, straightforward resolution

// When to use resolve():
// - You need to know which source provided the value
// - You want to track attempted sources
// - You need metadata for logging, billing, or debugging
// - You need to differentiate between "not found" and "found with null value"
```

## Best Practices

### 1. Use resolve() for Critical Values

```php
// Good: Use resolve() when you need to track the source
$result = $cascade->resolve('api-key', ['customer_id' => $customerId]);

if ($result->getSourceName() === 'platform') {
    $this->billing->record($customerId);
}

// OK: Use get() for simple lookups
$timeout = $cascade->get('timeout', default: 30);
```

### 2. Log Resolution Failures

```php
$result = $cascade->resolve('required-setting');

if (!$result->wasFound()) {
    $this->logger->error('Required setting not found', [
        'setting' => 'required-setting',
        'attempted' => $result->getAttemptedSources(),
    ]);
}
```

### 3. Leverage Metadata for Debugging

```php
if ($this->app->isDebug()) {
    $result = $cascade->resolve($key, $context);

    dump([
        'value' => $result->getValue(),
        'source' => $result->getSourceName(),
        'attempted' => $result->getAttemptedSources(),
        'metadata' => $result->getMetadata(),
    ]);
}
```

## Next Steps

- Learn about [Transformers](#doc-docs-transformers) to modify resolved values
- Use [Events](#doc-docs-events) to monitor resolution lifecycle
- Explore [Bulk Resolution](#doc-docs-bulk-resolution) for resolving multiple keys
- See [Advanced Usage](#doc-docs-advanced-usage) for complex patterns

<a id="doc-docs-sources"></a>

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

- Learn about [Named Resolvers](#doc-docs-named-resolvers) for managing multiple source configurations
- Explore [Result Metadata](#doc-docs-result-metadata) to track which source provided values
- Use [Transformers](#doc-docs-transformers) to modify resolved values
- Set up [Events](#doc-docs-events) for monitoring source queries

<a id="doc-docs-transformers"></a>

Transformers allow you to modify values after they've been resolved but before they're returned to the caller. Use them for decryption, type conversion, data enrichment, or any post-processing logic.

## Global Transformers

Apply transformations to all resolved values:

```php
use Cline\Cascade\Cascade;

$cascade = Cascade::from()
    ->fallbackTo($source)
    ->transform(fn($value, $result) => [
        'value' => $value,
        'source' => $result->getSourceName(),
        'cached' => false,
    ]);

$result = $cascade->get('api-key');
// Returns: ['value' => 'key-value', 'source' => 'database', 'cached' => false]
```

## Source-Specific Transformers

Transform values from specific sources:

```php
use Cline\Cascade\Source\CallbackSource;

$encryptedSource = new CallbackSource(
    name: 'database',
    resolver: fn($key) => $this->db->find($key),
    transformer: fn($row) => $this->decrypt($row->encrypted_value),
);

$cascade = Cascade::from()->fallbackTo($encryptedSource);

// Value is automatically decrypted when resolved
$apiKey = $cascade->get('api-key');
```

## Basic Transformations

### Type Conversion

Convert resolved values to specific types:

```php
// String to integer
$source = new CallbackSource(
    name: 'config',
    resolver: fn($key) => $this->config->get($key),
    transformer: fn($value) => (int) $value,
);

// String to boolean
$boolSource = new CallbackSource(
    name: 'flags',
    resolver: fn($key) => $this->db->find($key),
    transformer: fn($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
);

// JSON string to array
$jsonSource = new CallbackSource(
    name: 'json-config',
    resolver: fn($key) => $this->storage->read($key),
    transformer: fn($json) => json_decode($json, true),
);
```

### Value Enrichment

Add additional data to resolved values:

```php
$cascade = Cascade::from()
    ->fallbackTo($source)
    ->transform(function($value, $result) {
        return [
            'data' => $value,
            'metadata' => [
                'source' => $result->getSourceName(),
                'timestamp' => time(),
                'cached' => $result->getMetadata()['cached'] ?? false,
            ],
        ];
    });
```

## Decryption

Decrypt sensitive values after retrieval:

```php
use Cline\Cascade\Source\CallbackSource;

class EncryptedCredentialSource
{
    public function create(): CallbackSource
    {
        return new CallbackSource(
            name: 'encrypted-db',
            resolver: function(string $key, array $context) {
                return $this->db
                    ->table('credentials')
                    ->where('customer_id', $context['customer_id'])
                    ->where('key', $key)
                    ->first();
            },
            transformer: function($row) {
                return [
                    'api_key' => $this->decrypt($row->encrypted_api_key),
                    'api_secret' => $this->decrypt($row->encrypted_api_secret),
                    'environment' => $row->environment,
                ];
            },
        );
    }

    private function decrypt(string $encrypted): string
    {
        return openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $this->encryptionKey,
            0,
            substr($encrypted, 0, 16)
        );
    }
}
```

## Object Mapping

Map database rows to domain objects:

```php
$source = new CallbackSource(
    name: 'customer-db',
    resolver: fn($id) => $this->db->find($id),
    transformer: fn($row) => new Customer(
        id: CustomerId::from($row->id),
        name: $row->name,
        email: Email::from($row->email),
        createdAt: Carbon::parse($row->created_at),
    ),
);

$customer = $cascade->get('cust-123');
// Returns Customer object, not database row
```

## Chaining Transformers

Apply multiple transformations in sequence:

```php
// Using resolver-level transformer
$cascade = Cascade::from()
    ->fallbackTo(new CallbackSource(
        name: 'source',
        resolver: fn($key) => $this->storage->get($key),
        transformer: fn($value) => json_decode($value, true), // First: Parse JSON
    ))
    ->transform(fn($array) => new Config($array))  // Second: Create object
    ->transform(fn($config) => $config->validate()); // Third: Validate

// Or compose transformers manually
$transformer = fn($value) =>
    (new Config(json_decode($value, true)))->validate();

$source = new CallbackSource(
    name: 'config',
    resolver: fn($key) => $this->storage->get($key),
    transformer: $transformer,
);
```

## Conditional Transformation

Transform values based on conditions:

```php
$source = new CallbackSource(
    name: 'config',
    resolver: fn($key) => $this->db->find($key),
    transformer: function($value) {
        // Only decrypt if value looks encrypted
        if (str_starts_with($value, 'encrypted:')) {
            return $this->decrypt(substr($value, 10));
        }
        return $value;
    },
);
```

## Resolver-Level Transformers

Apply transformers to specific named resolvers:

```php
$cascade = new Cascade();

// Credentials resolver with decryption
$cascade->defineResolver('credentials')
    ->source('database', $dbSource)
    ->transform(fn($value) => $this->decrypt($value));

// Config resolver with JSON parsing
$cascade->defineResolver('config')
    ->source('storage', $storageSource)
    ->transform(fn($value) => json_decode($value, true));

// Each resolver has independent transformers
$credentials = $cascade->using('credentials')->get('api-key'); // Decrypted
$config = $cascade->using('config')->get('settings');           // JSON parsed
```

## Practical Examples

### API Response Formatting

Format responses consistently:

```php
class ApiResponseFormatter
{
    private Cascade $cascade;

    public function __construct()
    {
        $this->cascade = Cascade::from()
            ->fallbackTo($source)
            ->transform(function($value, $result) {
                return [
                    'data' => $value,
                    'meta' => [
                        'source' => $result->getSourceName(),
                        'cached' => false,
                    ],
                ];
            });
    }

    public function resolve(string $key, array $context = []): array
    {
        return $this->cascade->get($key, $context, default: [
            'data' => null,
            'meta' => ['source' => null, 'cached' => false],
        ]);
    }
}
```

### Credential Preparation

Prepare credentials in the expected format:

```php
$carrierSource = new CallbackSource(
    name: 'carrier-credentials',
    resolver: fn($carrier, $ctx) => $this->db->getCredentials($carrier, $ctx['customer_id']),
    transformer: function($row) {
        return [
            'auth' => [
                'username' => $row->api_key,
                'password' => $this->decrypt($row->api_secret),
            ],
            'config' => [
                'endpoint' => $row->endpoint_url,
                'timeout' => $row->timeout ?? 30,
            ],
        ];
    },
);

$credentials = $cascade->get('fedex', ['customer_id' => 'cust-123']);
// Returns ready-to-use credential structure
```

### Validation After Resolution

Validate values after retrieval:

```php
$source = new CallbackSource(
    name: 'config',
    resolver: fn($key) => $this->storage->get($key),
    transformer: function($value) {
        $validator = Validator::make(
            ['value' => $value],
            ['value' => 'required|integer|min:1|max:100']
        );

        if ($validator->fails()) {
            throw new InvalidConfigException($validator->errors());
        }

        return (int) $value;
    },
);
```

### Timestamp Conversion

Convert timestamps to Carbon instances:

```php
$source = new CallbackSource(
    name: 'events',
    resolver: fn($id) => $this->db->findEvent($id),
    transformer: function($row) {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'occurred_at' => Carbon::parse($row->occurred_at),
            'created_at' => Carbon::parse($row->created_at),
        ];
    },
);
```

### Denormalization

Enrich values with related data:

```php
$userSource = new CallbackSource(
    name: 'users',
    resolver: fn($id) => $this->db->findUser($id),
    transformer: function($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'organization' => $this->organizations->find($user->org_id),
            'permissions' => $this->permissions->forUser($user->id),
        ];
    },
);

$user = $cascade->get('user-123');
// Returns user with organization and permissions loaded
```

## Transformer Access to Result

Global transformers receive both the value and the Result object:

```php
$cascade = Cascade::from()
    ->fallbackTo($source)
    ->transform(function($value, $result) {
        // Access result metadata
        $source = $result->getSourceName();
        $metadata = $result->getMetadata();

        // Different transformation based on source
        return match($source) {
            'database' => $this->decrypt($value),
            'cache' => $value, // Already decrypted
            default => $value,
        };
    });
```

## Performance Considerations

### Lazy Transformation

Only transform when needed:

```php
class LazyTransformer
{
    public function __construct(
        private Cascade $cascade,
    ) {}

    public function get(string $key, array $context = []): LazyValue
    {
        $value = $this->cascade->get($key, $context);

        return new LazyValue($value, function($value) {
            // Expensive transformation only happens when accessed
            return $this->expensiveTransformation($value);
        });
    }
}
```

### Cached Transformations

Cache transformation results:

```php
$source = new CallbackSource(
    name: 'config',
    resolver: fn($key) => $this->storage->get($key),
    transformer: function($value) use (&$cache) {
        $cacheKey = 'transformed:' . md5($value);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $transformed = $this->expensiveTransform($value);
        $cache[$cacheKey] = $transformed;

        return $transformed;
    },
);
```

## Error Handling

Handle transformation failures gracefully:

```php
$source = new CallbackSource(
    name: 'json-config',
    resolver: fn($key) => $this->storage->get($key),
    transformer: function($value) {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('Invalid JSON in config', [
                'error' => $e->getMessage(),
                'value' => $value,
            ]);
            return [];
        }
    },
);
```

## Best Practices

### 1. Keep Transformers Pure

```php
// Good: Pure function
$transformer = fn($value) => strtoupper($value);

// Avoid: Side effects
$transformer = function($value) {
    $this->logger->info('Transforming value'); // Side effect
    return strtoupper($value);
};
```

### 2. Use Source Transformers for Source-Specific Logic

```php
// Good: Transformation specific to this source
$encryptedSource = new CallbackSource(
    name: 'encrypted-db',
    resolver: fn($key) => $this->db->find($key),
    transformer: fn($value) => $this->decrypt($value),
);

// Good: Global transformation for all sources
$cascade->transform(fn($value, $result) => [
    'value' => $value,
    'source' => $result->getSourceName(),
]);
```

### 3. Document Transformation Behavior

```php
/**
 * Credentials resolver with automatic decryption.
 *
 * Transforms encrypted database values into usable credentials:
 * - Decrypts api_key and api_secret
 * - Parses JSON config if present
 * - Validates required fields
 */
$cascade->defineResolver('credentials')
    ->source('database', $dbSource)
    ->transform(fn($value) => $this->decrypt($value));
```

## Next Steps

- Use [Events](#doc-docs-events) to monitor transformation performance
- Explore [Bulk Resolution](#doc-docs-bulk-resolution) with transformers
- Learn about [Advanced Usage](#doc-docs-advanced-usage) for complex transformation patterns
- See [Cookbook](#doc-docs-cookbook) for real-world transformation examples
