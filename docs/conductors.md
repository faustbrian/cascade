---
title: Conductors
description: Understand the fluent conductor pattern for building resolution chains
---

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

- [Sources](./sources.md) - Understand built-in source types
- [Events](./events.md) - Monitor resolution lifecycle
- [Cookbook](./cookbook.md) - Real-world patterns and recipes
