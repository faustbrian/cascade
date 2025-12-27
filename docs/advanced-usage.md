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

- Apply these patterns in [Cookbook](./cookbook.md) recipes
- Use advanced sources with [Events](./events.md) for monitoring
- Combine with [Repositories](./repositories.md) for dynamic configuration
- Explore [Bulk Resolution](./bulk-resolution.md) for batch optimization
