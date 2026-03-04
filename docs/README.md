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

- Learn about [Basic Usage](./basic-usage.md) for detailed examples
- Explore [Sources](./sources.md) to understand different source types
- Check out [Named Resolvers](./named-resolvers.md) for managing multiple configurations
- See [Events](./events.md) for monitoring resolution lifecycle
