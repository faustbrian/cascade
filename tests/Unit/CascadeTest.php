<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Cascade;
use Cline\Cascade\Event\ResolutionFailed;
use Cline\Cascade\Event\SourceQueried;
use Cline\Cascade\Event\ValueResolved;
use Cline\Cascade\Exception\NoResolversRegisteredException;
use Cline\Cascade\Exception\ResolutionFailedForKeyException;
use Cline\Cascade\Exception\ResolverNotFoundWithSuggestionsException;
use Cline\Cascade\Result;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\NullSource;

describe('Cascade facade', function (): void {
    beforeEach(function (): void {
        $this->cascade = new Cascade();
    });

    describe('source conductor', function (): void {
        test('resolves value from first matching source', function (): void {
            $value = $this->cascade->from(
                new ArraySource('primary', ['key' => 'primary-value']),
            )
                ->fallbackTo(
                    new ArraySource('fallback', ['key' => 'fallback-value']),
                )
                ->get('key');

            expect($value)->toBe('primary-value');
        });

        test('falls back to next source if first source returns null', function (): void {
            $value = $this->cascade->from(
                new NullSource('null-source'),
            )
                ->fallbackTo(
                    new ArraySource('fallback', ['key' => 'fallback-value']),
                )
                ->get('key');

            expect($value)->toBe('fallback-value');
        });

        test('returns null if no source has value', function (): void {
            $value = $this->cascade->from(
                new NullSource('null-source'),
            )
                ->get('key');

            expect($value)->toBeNull();
        });

        test('returns default if not found', function (): void {
            $value = $this->cascade->from(
                new NullSource('null-source'),
            )
                ->get('key', default: 'default-value');

            expect($value)->toBe('default-value');
        });

        test('returns callable default if not found', function (): void {
            $value = $this->cascade->from(
                new NullSource('null-source'),
            )
                ->get('key', default: fn (): string => 'callable-default');

            expect($value)->toBe('callable-default');
        });

        test('resolves with context', function (): void {
            $value = $this->cascade->from(
                new CallbackSource(
                    'context-aware',
                    fn (string $key, array $context): ?string => $context['env'] === 'production' ? 'prod-value' : null,
                ),
            )->get('key', context: ['env' => 'production']);

            expect($value)->toBe('prod-value');
        });

        test('skips sources that do not support the key', function (): void {
            $value = $this->cascade->from(
                new CallbackSource(
                    'conditional',
                    fn (): string => 'conditional-value',
                    fn (string $key): bool => $key === 'supported-key',
                ),
            )
                ->fallbackTo(
                    new ArraySource('fallback', ['unsupported-key' => 'fallback-value']),
                )
                ->get('unsupported-key');

            expect($value)->toBe('fallback-value');
        });

        test('resolves with full result metadata', function (): void {
            $result = $this->cascade->from(
                new ArraySource('primary', ['key' => 'value']),
            )
                ->resolve('key');

            expect($result)->toBeInstanceOf(Result::class);
            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe('value');
            expect($result->getSourceName())->toBe('primary');
        });

        test('getOrFail returns value when found', function (): void {
            $value = $this->cascade->from(
                new ArraySource('primary', ['key' => 'value']),
            )
                ->getOrFail('key');

            expect($value)->toBe('value');
        });

        test('getOrFail throws exception when not found', function (): void {
            expect(fn () => $this->cascade->from(
                new NullSource('null-source'),
            )->getOrFail('key'))
                ->toThrow(ResolutionFailedForKeyException::class);
        });

        test('resolves multiple keys with getMany', function (): void {
            $results = $this->cascade->from(
                new ArraySource('source', [
                    'key1' => 'value1',
                    'key2' => 'value2',
                    'key3' => 'value3',
                ]),
            )->getMany(['key1', 'key2', 'key3']);

            expect($results)->toHaveCount(3);
            expect($results['key1']->getValue())->toBe('value1');
            expect($results['key2']->getValue())->toBe('value2');
            expect($results['key3']->getValue())->toBe('value3');
        });

        test('can be registered as named resolver', function (): void {
            $this->cascade->from(
                new ArraySource('source', ['key' => 'value']),
            )
                ->as('my-resolver');

            $value = $this->cascade->using('my-resolver')->get('key');

            expect($value)->toBe('value');
        });

        test('transformers apply to resolved values', function (): void {
            $value = $this->cascade->from(
                new ArraySource('source', ['key' => 'value']),
            )
                ->transform(fn ($value) => mb_strtoupper($value))
                ->get('key');

            expect($value)->toBe('VALUE');
        });

        test('auto-increments priority for fallback sources', function (): void {
            $value = $this->cascade->from(
                new ArraySource('first', ['key' => 'first']),
            )
                ->fallbackTo(
                    new ArraySource('second', ['key' => 'second']),
                )
                ->fallbackTo(
                    new ArraySource('third', ['key' => 'third']),
                )
                ->get('key');

            // First source should win
            expect($value)->toBe('first');
        });
    });

    describe('named resolvers', function (): void {
        test('creates and retrieves named resolver', function (): void {
            $resolver = $this->cascade->defineResolver('custom');

            expect($resolver->getName())->toBe('custom');
            expect($this->cascade->hasResolver('custom'))->toBeTrue();
        });

        test('named resolvers are independent', function (): void {
            $this->cascade->defineResolver('resolver-1')
                ->source('source-1', new ArraySource('source-1', ['key' => 'value-1']));

            $this->cascade->defineResolver('resolver-2')
                ->source('source-2', new ArraySource('source-2', ['key' => 'value-2']));

            $value1 = $this->cascade->using('resolver-1')->get('key');
            $value2 = $this->cascade->using('resolver-2')->get('key');

            expect($value1)->toBe('value-1');
            expect($value2)->toBe('value-2');
        });

        test('throws exception when resolver not found', function (): void {
            expect(fn () => $this->cascade->using('non-existent'))
                ->toThrow(NoResolversRegisteredException::class);
        });

        test('throws exception with suggestions when resolver not found', function (): void {
            $this->cascade->defineResolver('config');
            $this->cascade->defineResolver('environment');

            expect(fn () => $this->cascade->using('non-existent'))
                ->toThrow(ResolverNotFoundWithSuggestionsException::class);
        });

        test('checks if resolver exists', function (): void {
            $this->cascade->defineResolver('custom');

            expect($this->cascade->hasResolver('custom'))->toBeTrue();
            expect($this->cascade->hasResolver('non-existent'))->toBeFalse();
        });
    });

    describe('resolution conductor', function (): void {
        beforeEach(function (): void {
            $this->cascade->defineResolver('test-resolver')
                ->source('source', new ArraySource('source', [
                    'key' => 'value',
                    'name' => 'John',
                ]));
        });

        test('resolves using named resolver', function (): void {
            $value = $this->cascade->using('test-resolver')->get('key');

            expect($value)->toBe('value');
        });

        test('binds context from array', function (): void {
            $this->cascade->defineResolver('context-resolver')
                ->source('source', new CallbackSource(
                    'source',
                    fn (string $key, array $ctx): mixed => $ctx['user_id'] ?? null,
                ));

            $value = $this->cascade->using('context-resolver')
                ->for(['user_id' => 123])
                ->get('key');

            expect($value)->toBe(123);
        });

        test('binds context from model with getKey', function (): void {
            $model = new class()
            {
                public function getKey(): int
                {
                    return 456;
                }
            };

            $this->cascade->defineResolver('model-resolver')
                ->source('source', new CallbackSource(
                    'source',
                    fn (string $key, array $ctx): mixed => $ctx[mb_strtolower(class_basename($model::class)).'_id'] ?? null,
                ));

            $value = $this->cascade->using('model-resolver')
                ->for($model)
                ->get('key');

            expect($value)->toBe(456);
        });

        test('applies transformers to resolved values', function (): void {
            $value = $this->cascade->using('test-resolver')
                ->transform(fn ($value) => mb_strtoupper($value))
                ->get('key');

            expect($value)->toBe('VALUE');
        });

        test('resolves with full result metadata', function (): void {
            $result = $this->cascade->using('test-resolver')->resolve('key');

            expect($result)->toBeInstanceOf(Result::class);
            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe('value');
        });

        test('getOrFail throws when not found', function (): void {
            expect(fn () => $this->cascade->using('test-resolver')->getOrFail('missing'))
                ->toThrow(ResolutionFailedForKeyException::class);
        });

        test('resolves multiple keys', function (): void {
            $results = $this->cascade->using('test-resolver')->getMany(['key', 'name']);

            expect($results)->toHaveCount(2);
            expect($results['key']->getValue())->toBe('value');
            expect($results['name']->getValue())->toBe('John');
        });

        test('returns callable default when value not found', function (): void {
            $this->cascade->defineResolver('null-resolver')
                ->source('source', new NullSource('null-source'));

            $value = $this->cascade->using('null-resolver')
                ->get('missing', fn (): string => 'callable-default');

            expect($value)->toBe('callable-default');
        });

        test('applies transformers in getOrFail', function (): void {
            $value = $this->cascade->using('test-resolver')
                ->transform(fn ($value) => mb_strtoupper($value))
                ->transform(fn ($value): string => $value.'!')
                ->getOrFail('key');

            expect($value)->toBe('VALUE!');
        });

        test('binds context from model with toCascadeContext returning non-array', function (): void {
            $model = new class()
            {
                public function getKey(): int
                {
                    return 789;
                }

                public function toCascadeContext(): string
                {
                    return 'invalid-not-array';
                }
            };

            $this->cascade->defineResolver('custom-context-resolver')
                ->source('source', new CallbackSource(
                    'source',
                    fn (string $key, array $ctx): mixed => $ctx[mb_strtolower(class_basename($model::class)).'_id'] ?? null,
                ));

            $value = $this->cascade->using('custom-context-resolver')
                ->for($model)
                ->get('key');

            expect($value)->toBe(789);
        });

        test('binds context from model with toCascadeContext returning array', function (): void {
            $model = new class()
            {
                public function getKey(): int
                {
                    return 999;
                }

                public function toCascadeContext(): array
                {
                    return ['custom_key' => 'custom_value', 'user_type' => 'premium'];
                }
            };

            $this->cascade->defineResolver('custom-context-array-resolver')
                ->source('source', new CallbackSource(
                    'source',
                    fn (string $key, array $ctx): mixed => $ctx['custom_key'] ?? null,
                ));

            $value = $this->cascade->using('custom-context-array-resolver')
                ->for($model)
                ->get('key');

            expect($value)->toBe('custom_value');
        });
    });

    describe('events', function (): void {
        test('emits SourceQueried event for each attempted source', function (): void {
            $queriedSources = [];

            $this->cascade->onSourceQueried(function (SourceQueried $event) use (&$queriedSources): void {
                $queriedSources[] = $event->sourceName;
            });

            $this->cascade->from(
                new NullSource('null-1'),
            )
                ->fallbackTo(
                    new ArraySource('array', ['key' => 'value']),
                )
                ->resolve('key');

            expect($queriedSources)->toBe(['null-1', 'array']);
        });

        test('emits ValueResolved event when value is found', function (): void {
            $resolvedEvent = null;

            $this->cascade->onResolved(function (ValueResolved $event) use (&$resolvedEvent): void {
                $resolvedEvent = $event;
            });

            $this->cascade->from(
                new ArraySource('source', ['key' => 'value']),
            )
                ->resolve('key');

            expect($resolvedEvent)->toBeInstanceOf(ValueResolved::class);
            expect($resolvedEvent->key)->toBe('key');
            expect($resolvedEvent->value)->toBe('value');
            expect($resolvedEvent->sourceName)->toBe('source');
            expect($resolvedEvent->durationMs)->toBeGreaterThan(0);
        });

        test('emits ResolutionFailed event when not found', function (): void {
            $failedEvent = null;

            $this->cascade->onFailed(function (ResolutionFailed $event) use (&$failedEvent): void {
                $failedEvent = $event;
            });

            $this->cascade->from(
                new NullSource('null-source'),
            )
                ->resolve('key');

            expect($failedEvent)->toBeInstanceOf(ResolutionFailed::class);
            expect($failedEvent->key)->toBe('key');
            expect($failedEvent->attemptedSources)->toBe(['null-source']);
        });

        test('does not emit ValueResolved event when not found', function (): void {
            $resolvedCalled = false;

            $this->cascade->onResolved(function () use (&$resolvedCalled): void {
                $resolvedCalled = true;
            });

            $this->cascade->from(
                new NullSource('null-source'),
            )
                ->resolve('key');

            expect($resolvedCalled)->toBeFalse();
        });

        test('does not emit ResolutionFailed event when value is found', function (): void {
            $failedCalled = false;

            $this->cascade->onFailed(function () use (&$failedCalled): void {
                $failedCalled = true;
            });

            $this->cascade->from(
                new ArraySource('source', ['key' => 'value']),
            )
                ->resolve('key');

            expect($failedCalled)->toBeFalse();
        });
    });

    describe('priority ordering', function (): void {
        test('queries sources in priority order', function (): void {
            $value = $this->cascade->from(
                new ArraySource('low-priority', ['key' => 'low']),
                priority: 10,
            )
                ->addSource(
                    new ArraySource('high-priority', ['key' => 'high']),
                    priority: 1,
                )
                ->addSource(
                    new ArraySource('medium-priority', ['key' => 'medium']),
                    priority: 5,
                )
                ->get('key');

            expect($value)->toBe('high');
        });

        test('default priority is 0', function (): void {
            $value = $this->cascade->from(
                new ArraySource('default', ['key' => 'default']),
            )
                ->addSource(
                    new ArraySource('low-priority', ['key' => 'low']),
                    priority: 10,
                )
                ->get('key');

            expect($value)->toBe('default');
        });
    });
});
