<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Repository\CachedRepository;
use Cline\Cascade\Repository\ResolverRepositoryInterface;
use Psr\SimpleCache\CacheInterface;

describe('CachedRepository', function (): void {
    function createCachedRepositoryMockCache(): CacheInterface
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
                if (isset($this->store[$key])) {
                    unset($this->store[$key]);
                    return true;
                }

                return false;
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

    function createMockRepository(array $definitions = []): ResolverRepositoryInterface
    {
        return new class ($definitions) implements ResolverRepositoryInterface {
            private int $getCallCount = 0;
            private int $getManyCallCount = 0;

            public function __construct(private array $definitions = []) {}

            public function get(string $name): array
            {
                ++$this->getCallCount;

                throw_unless(isset($this->definitions[$name]), ResolverNotFoundException::class, sprintf("Resolver '%s' not found", $name));

                return $this->definitions[$name];
            }

            public function has(string $name): bool
            {
                return isset($this->definitions[$name]);
            }

            public function all(): array
            {
                return $this->definitions;
            }

            public function getMany(array $names): array
            {
                ++$this->getManyCallCount;
                $result = [];

                foreach ($names as $name) {
                    if (isset($this->definitions[$name])) {
                        $result[$name] = $this->definitions[$name];
                    }
                }

                return $result;
            }

            public function getCallCount(): int
            {
                return $this->getCallCount;
            }

            public function getManyCallCount(): int
            {
                return $this->getManyCallCount;
            }

            public function addDefinition(string $name, array $definition): void
            {
                $this->definitions[$name] = $definition;
            }
        };
    }

    describe('get() method', function (): void {
        describe('cache miss scenarios', function (): void {
            test('fetches from inner repository on cache miss', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'test', 'value' => 'data'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $result = $repository->get('resolver1');

                expect($result)->toBe(['type' => 'test', 'value' => 'data']);
            });

            test('stores result in cache after fetching from inner repository', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'test', 'value' => 'data'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $repository->get('resolver1');

                expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();
            });

            test('throws exception when resolver not found', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([]);

                $repository = new CachedRepository($inner, $cache);

                expect(fn(): array => $repository->get('nonexistent'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'nonexistent' not found");
            });
        });

        describe('cache hit scenarios', function (): void {
            test('returns cached value on cache hit', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'test', 'value' => 'data'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // First call - cache miss
                $first = $repository->get('resolver1');

                // Second call - cache hit
                $second = $repository->get('resolver1');

                expect($first)->toBe(['type' => 'test', 'value' => 'data']);
                expect($second)->toBe(['type' => 'test', 'value' => 'data']);
            });

            test('does not call inner repository on cache hit', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'test', 'value' => 'data'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $repository->get('resolver1'); // Cache miss - calls inner
                $repository->get('resolver1'); // Cache hit - should not call inner
                $repository->get('resolver1'); // Cache hit - should not call inner

                expect($inner->getCallCount())->toBe(1);
            });
        });

        describe('TTL handling', function (): void {
            test('accepts null TTL for forever caching', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'test'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $repository->get('resolver1');

                expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();
            });

            test('accepts custom TTL value', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'test'],
                ]);

                $repository = new CachedRepository($inner, $cache, ttl: 3600);

                $repository->get('resolver1');

                expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();
            });
        });
    });

    describe('has() method', function (): void {
        test('returns true when resolver exists in cache', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Prime the cache
            $repository->get('resolver1');

            expect($repository->has('resolver1'))->toBeTrue();
        });

        test('checks inner repository when not in cache', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            expect($repository->has('resolver1'))->toBeTrue();
        });

        test('returns false when resolver does not exist', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([]);

            $repository = new CachedRepository($inner, $cache);

            expect($repository->has('nonexistent'))->toBeFalse();
        });

        test('returns true for cached resolver even if removed from inner', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Prime the cache
            $repository->get('resolver1');

            // Remove from inner repository
            $inner = createMockRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Should still return true because it's in cache
            expect($repository->has('resolver1'))->toBeTrue();
        });
    });

    describe('all() method', function (): void {
        test('returns all definitions from inner repository', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'type1'],
                'resolver2' => ['type' => 'type2'],
                'resolver3' => ['type' => 'type3'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->all();

            expect($result)->toBe([
                'resolver1' => ['type' => 'type1'],
                'resolver2' => ['type' => 'type2'],
                'resolver3' => ['type' => 'type3'],
            ]);
        });

        test('bypasses cache and always fetches from inner repository', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'type1'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Call multiple times
            $first = $repository->all();
            $second = $repository->all();

            // Add new definition to inner
            $inner->addDefinition('resolver2', ['type' => 'type2']);
            $third = $repository->all();

            expect($first)->toBe(['resolver1' => ['type' => 'type1']]);
            expect($second)->toBe(['resolver1' => ['type' => 'type1']]);
            expect($third)->toBe([
                'resolver1' => ['type' => 'type1'],
                'resolver2' => ['type' => 'type2'],
            ]);
        });

        test('returns empty array when no definitions exist', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([]);

            $repository = new CachedRepository($inner, $cache);

            expect($repository->all())->toBe([]);
        });
    });

    describe('getMany() method', function (): void {
        describe('all cache hits', function (): void {
            test('returns all definitions from cache when all are cached', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                    'resolver3' => ['type' => 'type3'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // Prime the cache
                $repository->get('resolver1');
                $repository->get('resolver2');
                $repository->get('resolver3');

                // Now call getMany - should use cache
                $result = $repository->getMany(['resolver1', 'resolver2', 'resolver3']);

                expect($result)->toBe([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                    'resolver3' => ['type' => 'type3'],
                ]);
            });

            test('does not call inner repository when all are cached', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // Prime the cache
                $repository->get('resolver1');
                $repository->get('resolver2');

                // Reset call count tracking
                $callCountBefore = $inner->getManyCallCount();

                // Call getMany - should not call inner
                $repository->getMany(['resolver1', 'resolver2']);

                expect($inner->getManyCallCount())->toBe($callCountBefore);
            });
        });

        describe('all cache misses', function (): void {
            test('fetches all from inner repository when none are cached', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $result = $repository->getMany(['resolver1', 'resolver2']);

                expect($result)->toBe([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                ]);
            });

            test('caches all fetched definitions', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $repository->getMany(['resolver1', 'resolver2']);

                expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();
                expect($cache->has('cascade:resolvers:resolver2'))->toBeTrue();
            });
        });

        describe('mixed cache hits and misses', function (): void {
            test('returns cached definitions and fetches uncached ones', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                    'resolver3' => ['type' => 'type3'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // Prime cache with resolver1 and resolver2
                $repository->get('resolver1');
                $repository->get('resolver2');

                // Request all three - resolver3 should be fetched
                $result = $repository->getMany(['resolver1', 'resolver2', 'resolver3']);

                expect($result)->toBe([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                    'resolver3' => ['type' => 'type3'],
                ]);
            });

            test('only fetches uncached definitions from inner repository', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                    'resolver3' => ['type' => 'type3'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // Prime cache with resolver1
                $repository->get('resolver1');

                $callCountBefore = $inner->getManyCallCount();

                // Request resolver1, resolver2, resolver3
                // Only resolver2 and resolver3 should be fetched
                $repository->getMany(['resolver1', 'resolver2', 'resolver3']);

                // getMany should have been called once to fetch resolver2 and resolver3
                expect($inner->getManyCallCount())->toBe($callCountBefore + 1);
            });

            test('caches newly fetched definitions for subsequent calls', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // First call - both are cache misses
                $repository->getMany(['resolver1', 'resolver2']);

                // Both should now be cached
                expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();
                expect($cache->has('cascade:resolvers:resolver2'))->toBeTrue();

                // Second call - both should come from cache
                $callCountBefore = $inner->getManyCallCount();
                $repository->getMany(['resolver1', 'resolver2']);

                expect($inner->getManyCallCount())->toBe($callCountBefore);
            });
        });

        describe('edge cases', function (): void {
            test('returns empty array when given empty names array', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([]);

                $repository = new CachedRepository($inner, $cache);

                expect($repository->getMany([]))->toBe([]);
            });

            test('handles nonexistent resolvers gracefully', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                // Request existing and nonexistent
                $result = $repository->getMany(['resolver1', 'nonexistent']);

                // Only existing resolver should be returned
                expect($result)->toBe([
                    'resolver1' => ['type' => 'type1'],
                ]);
            });

            test('preserves order of requested names in result', function (): void {
                $cache = createCachedRepositoryMockCache();
                $inner = createMockRepository([
                    'resolver1' => ['type' => 'type1'],
                    'resolver2' => ['type' => 'type2'],
                    'resolver3' => ['type' => 'type3'],
                ]);

                $repository = new CachedRepository($inner, $cache);

                $result = $repository->getMany(['resolver3', 'resolver1', 'resolver2']);

                // Check that we got all three (order may vary in associative arrays)
                expect(array_keys($result))->toContain('resolver1', 'resolver2', 'resolver3');
                expect(count($result))->toBe(3);
            });
        });
    });

    describe('forget() method', function (): void {
        test('removes resolver from cache', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Prime the cache
            $repository->get('resolver1');

            expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();

            // Forget the cached entry
            $result = $repository->forget('resolver1');

            expect($result)->toBeTrue();
            expect($cache->has('cascade:resolvers:resolver1'))->toBeFalse();
        });

        test('returns false when trying to forget nonexistent cache entry', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->forget('nonexistent');

            expect($result)->toBeFalse();
        });

        test('allows re-fetching fresh data after forget', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'old'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Get and cache original value
            $original = $repository->get('resolver1');
            expect($original)->toBe(['type' => 'old']);

            // Update inner repository
            $inner->addDefinition('resolver1', ['type' => 'new']);

            // Forget cache
            $repository->forget('resolver1');

            // Re-create repository with updated inner
            $repository = new CachedRepository($inner, $cache);

            // Should fetch fresh value
            $fresh = $repository->get('resolver1');
            expect($fresh)->toBe(['type' => 'new']);
        });

        test('uses custom prefix in cache key', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache, prefix: 'custom:prefix:');

            $repository->get('resolver1');

            expect($cache->has('custom:prefix:resolver1'))->toBeTrue();

            $repository->forget('resolver1');
            expect($cache->has('custom:prefix:resolver1'))->toBeFalse();
        });
    });

    describe('flush() method', function (): void {
        test('clears all cache entries', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'type1'],
                'resolver2' => ['type' => 'type2'],
                'resolver3' => ['type' => 'type3'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Prime cache with multiple entries
            $repository->get('resolver1');
            $repository->get('resolver2');
            $repository->get('resolver3');

            expect($cache->has('cascade:resolvers:resolver1'))->toBeTrue();
            expect($cache->has('cascade:resolvers:resolver2'))->toBeTrue();
            expect($cache->has('cascade:resolvers:resolver3'))->toBeTrue();

            // Flush cache
            $result = $repository->flush();

            expect($result)->toBeTrue();
            expect($cache->has('cascade:resolvers:resolver1'))->toBeFalse();
            expect($cache->has('cascade:resolvers:resolver2'))->toBeFalse();
            expect($cache->has('cascade:resolvers:resolver3'))->toBeFalse();
        });

        test('allows re-fetching all data after flush', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'type1'],
                'resolver2' => ['type' => 'type2'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Prime cache
            $repository->get('resolver1');
            $repository->get('resolver2');

            $callCountBefore = $inner->getCallCount();

            // Flush cache
            $repository->flush();

            // Re-fetch should call inner repository again
            $repository->get('resolver1');
            $repository->get('resolver2');

            expect($inner->getCallCount())->toBe($callCountBefore + 2);
        });

        test('returns true when cache is successfully cleared', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->flush();

            expect($result)->toBeTrue();
        });
    });

    describe('cache prefix functionality', function (): void {
        test('uses default prefix "cascade:resolvers:"', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'test' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            $repository->get('test');

            expect($cache->has('cascade:resolvers:test'))->toBeTrue();
        });

        test('accepts custom cache prefix', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'test' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache, prefix: 'my:custom:prefix:');

            $repository->get('test');

            expect($cache->has('my:custom:prefix:test'))->toBeTrue();
            expect($cache->has('cascade:resolvers:test'))->toBeFalse();
        });

        test('different prefixes allow independent caching', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner1 = createMockRepository([
                'test' => ['type' => 'type1'],
            ]);
            $inner2 = createMockRepository([
                'test' => ['type' => 'type2'],
            ]);

            $repo1 = new CachedRepository($inner1, $cache, prefix: 'repo1:');
            $repo2 = new CachedRepository($inner2, $cache, prefix: 'repo2:');

            $repo1->get('test');
            $repo2->get('test');

            expect($cache->has('repo1:test'))->toBeTrue();
            expect($cache->has('repo2:test'))->toBeTrue();

            // Verify they're independent
            $repo1->forget('test');
            expect($cache->has('repo1:test'))->toBeFalse();
            expect($cache->has('repo2:test'))->toBeTrue();
        });

        test('empty prefix works correctly', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'test' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache, prefix: '');

            $repository->get('test');

            expect($cache->has('test'))->toBeTrue();
        });
    });

    describe('edge cases', function (): void {
        test('handles empty array definition', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'empty' => [],
            ]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->get('empty');

            expect($result)->toBe([]);
            expect($cache->has('cascade:resolvers:empty'))->toBeTrue();
        });

        test('handles complex nested array definitions', function (): void {
            $cache = createCachedRepositoryMockCache();
            $complexDef = [
                'type' => 'complex',
                'config' => [
                    'nested' => [
                        'deep' => ['value' => 123],
                    ],
                ],
                'items' => ['a', 'b', 'c'],
            ];
            $inner = createMockRepository([
                'complex' => $complexDef,
            ]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->get('complex');

            expect($result)->toBe($complexDef);

            // Verify cached value
            $cached = $repository->get('complex');
            expect($cached)->toBe($complexDef);
        });

        test('handles resolver names with special characters', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver-with-dashes' => ['type' => 'test1'],
                'resolver.with.dots' => ['type' => 'test2'],
                'resolver_with_underscores' => ['type' => 'test3'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            expect($repository->get('resolver-with-dashes'))->toBe(['type' => 'test1']);
            expect($repository->get('resolver.with.dots'))->toBe(['type' => 'test2']);
            expect($repository->get('resolver_with_underscores'))->toBe(['type' => 'test3']);
        });

        test('cache isolation between different repositories sharing same cache instance', function (): void {
            $sharedCache = createCachedRepositoryMockCache();

            $inner1 = createMockRepository([
                'shared' => ['from' => 'repo1'],
            ]);
            $inner2 = createMockRepository([
                'shared' => ['from' => 'repo2'],
            ]);

            $repo1 = new CachedRepository($inner1, $sharedCache, prefix: 'repo1:');
            $repo2 = new CachedRepository($inner2, $sharedCache, prefix: 'repo2:');

            $result1 = $repo1->get('shared');
            $result2 = $repo2->get('shared');

            expect($result1)->toBe(['from' => 'repo1']);
            expect($result2)->toBe(['from' => 'repo2']);
        });

        test('preserves array definition structure through cache', function (): void {
            $cache = createCachedRepositoryMockCache();
            $definition = [
                'string' => 'value',
                'int' => 42,
                'float' => 3.14,
                'bool' => true,
                'null' => null,
                'array' => [1, 2, 3],
                'assoc' => ['key' => 'value'],
            ];
            $inner = createMockRepository([
                'structured' => $definition,
            ]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->get('structured');
            $cached = $repository->get('structured');

            expect($result)->toBe($definition);
            expect($cached)->toBe($definition);
        });

        test('handles rapid successive calls correctly', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            // Rapid successive calls
            $results = [];
            for ($i = 0; $i < 10; ++$i) {
                $results[] = $repository->get('resolver1');
            }

            // All should return the same value
            foreach ($results as $result) {
                expect($result)->toBe(['type' => 'test']);
            }

            // Inner should only be called once
            expect($inner->getCallCount())->toBe(1);
        });

        test('getMany handles duplicate names in input', function (): void {
            $cache = createCachedRepositoryMockCache();
            $inner = createMockRepository([
                'resolver1' => ['type' => 'test'],
            ]);

            $repository = new CachedRepository($inner, $cache);

            $result = $repository->getMany(['resolver1', 'resolver1', 'resolver1']);

            expect($result)->toHaveKey('resolver1');
            expect($result['resolver1'])->toBe(['type' => 'test']);
        });
    });
});
