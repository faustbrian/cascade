<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Cline\Cascade\Exception\ResolutionFailedException;
use Cline\Cascade\Cascade;
use Cline\Cascade\Conductors\SourceConductor;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\NullSource;
use Psr\SimpleCache\CacheInterface;

describe('SourceConductor', function (): void {
    beforeEach(function (): void {
        $this->cascade = new Cascade();
    });

    describe('cache() method', function (): void {
        test('wraps last source in cache with default ttl', function (): void {
            $cache = createSourceConductorMockCache();

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            )->cache($cache);

            $value = $conductor->get('key');

            expect($value)->toBe('value');
        });

        test('wraps last source in cache with custom ttl', function (): void {
            $cache = createSourceConductorMockCache();

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            )->cache($cache, ttl: 3600);

            $value = $conductor->get('key');

            expect($value)->toBe('value');
        });

        test('wraps last source in cache with custom key generator', function (): void {
            $cache = createSourceConductorMockCache();
            $keyGenCalled = false;

            $keyGenerator = function (string $key, array $context) use (&$keyGenCalled): string {
                $keyGenCalled = true;

                return 'custom:' . $key;
            };

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            )->cache($cache, keyGenerator: $keyGenerator);

            $value = $conductor->get('key');

            expect($value)->toBe('value');
            expect($keyGenCalled)->toBeTrue();
        });

        test('cache() returns same conductor for chaining', function (): void {
            $cache = createSourceConductorMockCache();

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            )->cache($cache);

            expect($conductor)->toBeInstanceOf(SourceConductor::class);
        });

        test('cache() on empty sources returns conductor unchanged', function (): void {
            $cache = createSourceConductorMockCache();
            $conductor = new SourceConductor($this->cascade);

            $result = $conductor->cache($cache);

            expect($result)->toBe($conductor);
        });

        test('cache() preserves source priority', function (): void {
            $cache = createSourceConductorMockCache();

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('low-priority', ['key' => 'low']),
                priority: 10,
            )->cache($cache);

            $conductor->addSource(new ArraySource('high-priority', ['key' => 'high']), priority: 1);

            $value = $conductor->get('key');

            // High priority source should win
            expect($value)->toBe('high');
        });

        test('cache() names cached source correctly', function (): void {
            $cache = createSourceConductorMockCache();

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('original-name', ['key' => 'value']),
            )->cache($cache);

            $result = $conductor->resolve('key');

            expect($result->getSourceName())->toBe('original-name-cached');
        });

        test('multiple cache() calls wrap most recently added source', function (): void {
            $cache = createSourceConductorMockCache();

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('first', ['key' => 'first-value']),
            )
                ->cache($cache)
                ->fallbackTo(new ArraySource('second', ['key' => 'second-value']))
                ->cache($cache);

            $result = $conductor->resolve('key');

            // First source should be found and it should be cached
            expect($result->getSourceName())->toBe('first-cached');
        });

        test('cache() with all parameters works correctly', function (): void {
            $cache = createSourceConductorMockCache();
            $customKeyGen = fn(string $key, array $ctx): string => 'prefix:' . $key;

            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            )->cache(
                cache: $cache,
                ttl: 7200,
                keyGenerator: $customKeyGen,
            );

            $value = $conductor->get('key');

            expect($value)->toBe('value');
        });
    });

    describe('normalizeSource() with string', function (): void {
        test('string source creates CallbackSource', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                'string-source',
            );

            // String source uses context to resolve
            $value = $conductor->get('key', context: ['key' => 'context-value']);

            expect($value)->toBe('context-value');
        });

        test('string source returns null when key not in context', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                'string-source',
            );

            $value = $conductor->get('missing-key', context: ['key' => 'value']);

            expect($value)->toBeNull();
        });

        test('string source fallback chain works', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                'context-source',
            )->fallbackTo(new ArraySource('fallback', ['key' => 'fallback-value']));

            $value = $conductor->get('key', context: ['other' => 'value']);

            expect($value)->toBe('fallback-value');
        });

        test('multiple string sources can be chained', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                'first',
            )->fallbackTo('second')
                ->fallbackTo(new ArraySource('array', ['key' => 'array-value']));

            $value = $conductor->get('key', context: []);

            expect($value)->toBe('array-value');
        });
    });

    describe('normalizeSource() with array', function (): void {
        test('array source creates ArraySource', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                ['key' => 'value', 'other' => 'data'],
            );

            $value = $conductor->get('key');

            expect($value)->toBe('value');
        });

        test('array source generates unique name based on content', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                ['key' => 'value'],
            );

            $result = $conductor->resolve('key');

            expect($result->getSourceName())->toStartWith('array-');
        });

        test('different arrays generate different source names', function (): void {
            $conductor1 = SourceConductor::from(
                $this->cascade,
                ['key' => 'value1'],
            );

            $conductor2 = SourceConductor::from(
                $this->cascade,
                ['key' => 'value2'],
            );

            $result1 = $conductor1->resolve('key');
            $result2 = $conductor2->resolve('key');

            expect($result1->getSourceName())->not->toBe($result2->getSourceName());
        });

        test('empty array source returns null', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                [],
            );

            $value = $conductor->get('key');

            expect($value)->toBeNull();
        });
    });

    describe('normalizeSource() with SourceInterface', function (): void {
        test('SourceInterface instance is used directly', function (): void {
            $source = new ArraySource('direct', ['key' => 'value']);

            $conductor = SourceConductor::from(
                $this->cascade,
                $source,
            );

            $result = $conductor->resolve('key');

            expect($result->getSourceName())->toBe('direct');
        });

        test('CallbackSource instance works', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(string $key): string => 'callback-value',
            );

            $conductor = SourceConductor::from(
                $this->cascade,
                $source,
            );

            $value = $conductor->get('key');

            expect($value)->toBe('callback-value');
        });
    });

    describe('edge cases and error conditions', function (): void {
        test('cache on empty conductor does not throw', function (): void {
            $cache = createSourceConductorMockCache();
            $conductor = new SourceConductor($this->cascade);

            expect(fn(): SourceConductor => $conductor->cache($cache))->not->toThrow(Exception::class);
        });

        test('resolve without sources returns not found result', function (): void {
            $conductor = new SourceConductor($this->cascade);

            $result = $conductor->resolve('key');

            expect($result->wasFound())->toBeFalse();
        });

        test('get without sources returns null', function (): void {
            $conductor = new SourceConductor($this->cascade);

            $value = $conductor->get('key');

            expect($value)->toBeNull();
        });

        test('get without sources returns default', function (): void {
            $conductor = new SourceConductor($this->cascade);

            $value = $conductor->get('key', default: 'default-value');

            expect($value)->toBe('default-value');
        });

        test('getOrFail without sources throws exception', function (): void {
            $conductor = new SourceConductor($this->cascade);

            expect(fn(): mixed => $conductor->getOrFail('key'))
                ->toThrow(ResolutionFailedException::class);
        });

        test('getMany without sources returns empty results', function (): void {
            $conductor = new SourceConductor($this->cascade);

            $results = $conductor->getMany(['key1', 'key2']);

            expect($results)->toHaveCount(2);
            expect($results['key1']->wasFound())->toBeFalse();
            expect($results['key2']->wasFound())->toBeFalse();
        });

        test('anonymous conductor auto-generates resolver name', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            );

            // Use conductor without calling as()
            $value = $conductor->get('key');

            expect($value)->toBe('value');
        });

        test('named conductor can be used multiple times', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('test', ['key' => 'value']),
            )->as('reusable');

            $value1 = $conductor->get('key');
            $value2 = $conductor->get('key');

            expect($value1)->toBe('value');
            expect($value2)->toBe('value');
        });

        test('transform without sources does nothing', function (): void {
            $conductor = new SourceConductor($this->cascade);

            $transformCalled = false;
            $conductor->transform(function ($value) use (&$transformCalled) {
                $transformCalled = true;

                return $value;
            });

            $conductor->get('key');

            expect($transformCalled)->toBeFalse();
        });

        test('fallbackTo with null priority auto-increments from 0', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('first', ['key' => 'first']),
            );

            // This should get priority 10
            $conductor->fallbackTo(new ArraySource('second', ['key' => 'second']));

            $value = $conductor->get('key');

            // First source (priority 0) should win
            expect($value)->toBe('first');
        });

        test('fallbackTo auto-increments from highest existing priority', function (): void {
            $conductor = SourceConductor::from(
                $this->cascade,
                new ArraySource('first', ['key' => 'first']),
                priority: 20,
            );

            // This should get priority 30
            $conductor->fallbackTo(new ArraySource('second', ['key' => 'second']));

            $value = $conductor->get('key');

            // First source should still win
            expect($value)->toBe('first');
        });
    });

    describe('integration scenarios', function (): void {
        test('complex chain with cache, transform, and multiple sources', function (): void {
            $cache = createSourceConductorMockCache();

            $value = SourceConductor::from(
                $this->cascade,
                new NullSource('null'),
            )
                ->fallbackTo(['key' => 'array-value'])
                ->cache($cache)
                ->transform(fn($v) => strtoupper($v))
                ->get('key');

            expect($value)->toBe('ARRAY-VALUE');
        });

        test('string source with cache and transform', function (): void {
            $cache = createSourceConductorMockCache();

            $value = SourceConductor::from(
                $this->cascade,
                'context-source',
            )
                ->cache($cache)
                ->transform(fn($v): string => 'transformed:' . $v)
                ->get('key', context: ['key' => 'context-value']);

            expect($value)->toBe('transformed:context-value');
        });

        test('array source as fallback to string source', function (): void {
            $value = SourceConductor::from(
                $this->cascade,
                'string-source',
            )
                ->fallbackTo(['key' => 'fallback'])
                ->get('key', context: []);

            expect($value)->toBe('fallback');
        });

        test('cached source as primary with array fallback', function (): void {
            $cache = createSourceConductorMockCache();

            $value = SourceConductor::from(
                $this->cascade,
                new ArraySource('primary', ['key' => 'primary']),
            )
                ->cache($cache)
                ->fallbackTo(['key' => 'fallback'])
                ->get('key');

            expect($value)->toBe('primary');
        });

        test('multiple transformers with cached sources', function (): void {
            $cache = createSourceConductorMockCache();

            $value = SourceConductor::from(
                $this->cascade,
                ['key' => 'value'],
            )
                ->cache($cache)
                ->transform(fn($v) => strtoupper($v))
                ->transform(fn($v): string|array => str_replace('A', '@', $v))
                ->get('key');

            expect($value)->toBe('V@LUE');
        });

        test('registered conductor with cache can be reused', function (): void {
            $cache = createSourceConductorMockCache();

            SourceConductor::from(
                $this->cascade,
                ['key' => 'value', 'name' => 'test'],
            )
                ->cache($cache)
                ->as('cached-resolver');

            $value1 = $this->cascade->using('cached-resolver')->get('key');
            $value2 = $this->cascade->using('cached-resolver')->get('name');

            expect($value1)->toBe('value');
            expect($value2)->toBe('test');
        });
    });
});

/**
 * Create a simple in-memory PSR-16 cache for testing.
 */
function createSourceConductorMockCache(): CacheInterface
{
    return new class implements CacheInterface {
        private array $data = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
        {
            $this->data[$key] = $value;

            return true;
        }

        public function delete(string $key): bool
        {
            unset($this->data[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->data = [];

            return true;
        }

        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $this->data[$key] ?? $default;
            }

            return $result;
        }

        public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
        {
            foreach ($values as $key => $value) {
                $this->data[$key] = $value;
            }

            return true;
        }

        public function deleteMultiple(iterable $keys): bool
        {
            foreach ($keys as $key) {
                unset($this->data[$key]);
            }

            return true;
        }

        public function has(string $key): bool
        {
            return isset($this->data[$key]);
        }
    };
}
