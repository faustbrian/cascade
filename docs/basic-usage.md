---
title: Basic Usage
description: Learn the fundamentals of using Cascade for value resolution with defaults, context, and conditional sources
---

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

- [Conductors](/cascade/conductors) - Deep dive into conductor patterns
- [Sources](/cascade/sources) - Built-in source types
- [Named Resolvers](/cascade/named-resolvers) - Managing multiple resolvers
- [Events](/cascade/events) - Monitoring resolution lifecycle
