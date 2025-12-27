---
title: Bulk Resolution
description: Efficiently resolve multiple keys at once using getMany() in Cascade
---

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

- Use bulk resolution with [Events](/cascade/events) for batch monitoring
- Combine with [Transformers](/cascade/transformers) for batch processing
- Explore [Advanced Usage](/cascade/advanced-usage) for optimization patterns
- See [Cookbook](/cascade/cookbook) for real-world bulk resolution examples
