---
title: Transformers
description: Transform resolved values before they're returned using global and source-specific transformers in Cascade
---

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

- Use [Events](./events.md) to monitor transformation performance
- Explore [Bulk Resolution](./bulk-resolution.md) with transformers
- Learn about [Advanced Usage](./advanced-usage.md) for complex transformation patterns
- See [Cookbook](./cookbook.md) for real-world transformation examples
