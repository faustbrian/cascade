---
title: Result Metadata
description: Working with Result objects to access resolved values, source information, and attempted sources in Cascade
---

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

- Learn about [Transformers](./transformers.md) to modify resolved values
- Use [Events](./events.md) to monitor resolution lifecycle
- Explore [Bulk Resolution](./bulk-resolution.md) for resolving multiple keys
- See [Advanced Usage](./advanced-usage.md) for complex patterns
