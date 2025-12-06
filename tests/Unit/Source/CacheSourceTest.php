<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CacheSource;
use Cline\Cascade\Source\CallbackSource;
use Psr\SimpleCache\CacheInterface;

describe('CacheSource', function (): void {
    function createCacheSourceMockCache(): CacheInterface
    {
        return new class implements CacheInterface {
            private array $store = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->store[$key] ?? $default;
            }

            public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
            {
                $this->store[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            public function clear(): bool
            {
                $this->store = [];

                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                $result = [];
                foreach ($keys as $key) {
                    $result[$key] = $this->get($key, $default);
                }

                return $result;
            }

            public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
            {
                foreach ($values as $key => $value) {
                    $this->set($key, $value, $ttl);
                }

                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                foreach ($keys as $key) {
                    $this->delete($key);
                }

                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }
        };
    }

    describe('basic caching', function (): void {
        test('returns value from inner source on first call', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', ['key' => 'value']);
            $source = new CacheSource('cached', $inner, $cache);

            $value = $source->get('key', []);

            expect($value)->toBe('value');
        });

        test('caches resolved value for subsequent calls', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', function () use (&$callCount): string {
                ++$callCount;

                return 'value';
            });

            $source = new CacheSource('cached', $inner, $cache);

            $source->get('key', []);
            $source->get('key', []);
            $source->get('key', []);

            // Inner source should only be called once
            expect($callCount)->toBe(1);
        });

        test('returns cached value on subsequent calls', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', ['key' => 'fresh-value']);
            $source = new CacheSource('cached', $inner, $cache);

            $first = $source->get('key', []);
            $second = $source->get('key', []);

            expect($first)->toBe('fresh-value');
            expect($second)->toBe('fresh-value');
        });

        test('does not cache null values', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', function () use (&$callCount): null {
                ++$callCount;

                return null;
            });

            $source = new CacheSource('cached', $inner, $cache);

            $source->get('key', []);
            $source->get('key', []);
            $source->get('key', []);

            // Inner source should be called each time since null is not cached
            expect($callCount)->toBe(3);
        });
    });

    describe('cache key generation', function (): void {
        test('generates unique cache keys for different keys', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', [
                'key1' => 'value1',
                'key2' => 'value2',
            ]);
            $source = new CacheSource('cached', $inner, $cache);

            $value1 = $source->get('key1', []);
            $value2 = $source->get('key2', []);

            expect($value1)->toBe('value1');
            expect($value2)->toBe('value2');
        });

        test('generates different cache keys for same key with different context', function (): void {
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', fn(string $key, array $context): mixed => $context['env'] ?? 'default');

            $source = new CacheSource('cached', $inner, $cache);

            $prod = $source->get('key', ['env' => 'production']);
            $dev = $source->get('key', ['env' => 'development']);

            expect($prod)->toBe('production');
            expect($dev)->toBe('development');
        });

        test('uses custom key generator when provided', function (): void {
            $generatedKeys = [];
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', ['key' => 'value']);

            $source = new CacheSource(
                'cached',
                $inner,
                $cache,
                keyGenerator: function (string $key, array $context) use (&$generatedKeys): string {
                    $customKey = 'custom:' . $key;
                    $generatedKeys[] = $customKey;

                    return $customKey;
                },
            );

            $source->get('key', []);

            expect($generatedKeys)->toBe(['custom:key']);
        });

        test('custom key generator receives context', function (): void {
            $receivedContext = null;
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', ['key' => 'value']);

            $source = new CacheSource(
                'cached',
                $inner,
                $cache,
                keyGenerator: function (string $key, array $context) use (&$receivedContext): string {
                    $receivedContext = $context;

                    return 'key:' . $key;
                },
            );

            $source->get('key', ['env' => 'production']);

            expect($receivedContext)->toBe(['env' => 'production']);
        });
    });

    describe('TTL configuration', function (): void {
        test('uses default TTL of 300 seconds', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', ['key' => 'value']);
            $source = new CacheSource('cached', $inner, $cache);

            $metadata = $source->getMetadata();

            expect($metadata['ttl'])->toBe(300);
        });

        test('accepts custom TTL', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', ['key' => 'value']);
            $source = new CacheSource('cached', $inner, $cache, ttl: 3600);

            $metadata = $source->getMetadata();

            expect($metadata['ttl'])->toBe(3600);
        });
    });

    describe('supports delegation', function (): void {
        test('delegates supports check to inner source', function (): void {
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource(
                'inner',
                fn(): string => 'value',
                fn(string $key): bool => str_starts_with($key, 'supported.'),
            );

            $source = new CacheSource('cached', $inner, $cache);

            expect($source->supports('supported.key', []))->toBeTrue();
            expect($source->supports('unsupported.key', []))->toBeFalse();
        });

        test('delegates supports with context to inner source', function (): void {
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource(
                'inner',
                fn(): string => 'value',
                fn(string $key, array $context): bool => ($context['enabled'] ?? false) === true,
            );

            $source = new CacheSource('cached', $inner, $cache);

            expect($source->supports('key', ['enabled' => true]))->toBeTrue();
            expect($source->supports('key', ['enabled' => false]))->toBeFalse();
        });
    });

    describe('name and metadata', function (): void {
        test('returns source name', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', []);
            $source = new CacheSource('custom-name', $inner, $cache);

            expect($source->getName())->toBe('custom-name');
        });

        test('metadata includes name and type', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', []);
            $source = new CacheSource('cached', $inner, $cache);

            $metadata = $source->getMetadata();

            expect($metadata['name'])->toBe('cached');
            expect($metadata['type'])->toBe('cache');
        });

        test('metadata includes inner source name', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner-source', []);
            $source = new CacheSource('cached', $inner, $cache);

            $metadata = $source->getMetadata();

            expect($metadata['inner'])->toBe('inner-source');
        });

        test('metadata includes TTL', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', []);
            $source = new CacheSource('cached', $inner, $cache, ttl: 1800);

            $metadata = $source->getMetadata();

            expect($metadata['ttl'])->toBe(1800);
        });

        test('metadata indicates if custom key generator provided', function (): void {
            $cache = createCacheSourceMockCache();
            $inner = new ArraySource('inner', []);

            $withGenerator = new CacheSource(
                'cached',
                $inner,
                $cache,
                keyGenerator: fn($key, $context): string => 'custom:' . $key,
            );

            $withoutGenerator = new CacheSource('cached', $inner, $cache);

            expect($withGenerator->getMetadata()['has_key_generator'])->toBeTrue();
            expect($withoutGenerator->getMetadata()['has_key_generator'])->toBeFalse();
        });
    });

    describe('caching scenarios', function (): void {
        test('expensive database query simulation', function (): void {
            $queryCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('database', function () use (&$queryCount): array {
                ++$queryCount;
                // Simulate expensive operation
                return ['id' => 1, 'name' => 'User'];
            });

            $source = new CacheSource('cached-db', $inner, $cache, ttl: 3600);

            // First call hits database
            $result1 = $source->get('user:1', []);
            expect($queryCount)->toBe(1);

            // Subsequent calls use cache
            $result2 = $source->get('user:1', []);
            $result3 = $source->get('user:1', []);
            expect($queryCount)->toBe(1);

            expect($result1)->toBe($result2);
            expect($result2)->toBe($result3);
        });

        test('external API call caching', function (): void {
            $apiCallCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('api', function (string $key) use (&$apiCallCount): string {
                ++$apiCallCount;

                return 'api-response-' . $key;
            });

            $source = new CacheSource('cached-api', $inner, $cache, ttl: 600);

            $source->get('endpoint', []);
            $source->get('endpoint', []);
            $source->get('endpoint', []);

            expect($apiCallCount)->toBe(1);
        });

        test('per-context caching', function (): void {
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('localized', fn(string $key, array $context): string => [
                'en' => 'Hello',
                'es' => 'Hola',
                'fr' => 'Bonjour',
            ][$context['lang'] ?? 'en']);

            $source = new CacheSource('cached-i18n', $inner, $cache);

            $english = $source->get('greeting', ['lang' => 'en']);
            $spanish = $source->get('greeting', ['lang' => 'es']);
            $french = $source->get('greeting', ['lang' => 'fr']);

            expect($english)->toBe('Hello');
            expect($spanish)->toBe('Hola');
            expect($french)->toBe('Bonjour');
        });
    });

    describe('edge cases', function (): void {
        test('caches empty string value', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', function () use (&$callCount): string {
                ++$callCount;

                return '';
            });

            $source = new CacheSource('cached', $inner, $cache);

            $source->get('key', []);
            $source->get('key', []);

            expect($callCount)->toBe(1);
        });

        test('caches zero value', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', function () use (&$callCount): int {
                ++$callCount;

                return 0;
            });

            $source = new CacheSource('cached', $inner, $cache);

            $source->get('key', []);
            $source->get('key', []);

            expect($callCount)->toBe(1);
        });

        test('caches false value', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', function () use (&$callCount): false {
                ++$callCount;

                return false;
            });

            $source = new CacheSource('cached', $inner, $cache);

            $source->get('key', []);
            $source->get('key', []);

            expect($callCount)->toBe(1);
        });

        test('caches array values', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();

            $inner = new CallbackSource('inner', function () use (&$callCount): array {
                ++$callCount;

                return ['key' => 'value'];
            });

            $source = new CacheSource('cached', $inner, $cache);

            $first = $source->get('key', []);
            $second = $source->get('key', []);

            expect($callCount)->toBe(1);
            expect($first)->toBe(['key' => 'value']);
            expect($second)->toBe(['key' => 'value']);
        });

        test('caches object values', function (): void {
            $callCount = 0;
            $cache = createCacheSourceMockCache();
            $object = (object) ['key' => 'value'];

            $inner = new CallbackSource('inner', function () use (&$callCount, $object) {
                ++$callCount;

                return $object;
            });

            $source = new CacheSource('cached', $inner, $cache);

            $first = $source->get('key', []);
            $second = $source->get('key', []);

            expect($callCount)->toBe(1);
            expect($first)->toBe($object);
            expect($second)->toBe($object);
        });
    });
});
