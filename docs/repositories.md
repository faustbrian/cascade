---
title: Repositories
description: Load resolver configurations from JSON, YAML, databases, and more using Cascade repositories
---

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

- Combine repositories with [Named Resolvers](/cascade/named-resolvers) for dynamic management
- Use [Events](/cascade/events) to monitor repository-loaded resolvers
- Explore [Advanced Usage](/cascade/advanced-usage) for complex repository patterns
- See [Cookbook](/cascade/cookbook) for real-world repository examples
