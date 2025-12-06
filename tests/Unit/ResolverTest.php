<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\ResolutionFailedException;
use Cline\Cascade\Resolver;
use Cline\Cascade\Result;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\NullSource;

describe('Resolver', function (): void {
    describe('basic resolution', function (): void {
        test('resolves value from first matching source', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('primary', new ArraySource('primary', ['key' => 'primary-value']));
            $resolver->source('fallback', new ArraySource('fallback', ['key' => 'fallback-value']));

            $value = $resolver->get('key');

            expect($value)->toBe('primary-value');
        });

        test('falls back to next source if first source returns null', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));
            $resolver->source('fallback', new ArraySource('fallback', ['key' => 'fallback-value']));

            $value = $resolver->get('key');

            expect($value)->toBe('fallback-value');
        });

        test('returns null if no source has value', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));

            $value = $resolver->get('key');

            expect($value)->toBeNull();
        });

        test('returns default value if not found', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));

            $value = $resolver->get('key', default: 'default-value');

            expect($value)->toBe('default-value');
        });

        test('returns callable default if not found', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));

            $value = $resolver->get('key', default: fn(): string => 'callable-default');

            expect($value)->toBe('callable-default');
        });

        test('resolves with context', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('context-aware', new CallbackSource(
                'context-aware',
                fn(string $key, array $context): mixed => $context['env'] ?? null,
            ));

            $value = $resolver->get('key', context: ['env' => 'production']);

            expect($value)->toBe('production');
        });
    });

    describe('priority ordering', function (): void {
        test('queries sources in priority order with lower values first', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('low', new ArraySource('low', ['key' => 'low']), priority: 10);
            $resolver->source('high', new ArraySource('high', ['key' => 'high']), priority: 1);
            $resolver->source('medium', new ArraySource('medium', ['key' => 'medium']), priority: 5);

            $value = $resolver->get('key');

            expect($value)->toBe('high');
        });

        test('default priority is 0', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('default', new ArraySource('default', ['key' => 'default']));
            $resolver->source('low', new ArraySource('low', ['key' => 'low']), priority: 10);

            $value = $resolver->get('key');

            expect($value)->toBe('default');
        });

        test('negative priority values come before 0', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('default', new ArraySource('default', ['key' => 'default']), priority: 0);
            $resolver->source('high', new ArraySource('high', ['key' => 'high']), priority: -10);

            $value = $resolver->get('key');

            expect($value)->toBe('high');
        });

        test('equal priority sources maintain insertion order', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('first', new ArraySource('first', ['key' => 'first']), priority: 5);
            $resolver->source('second', new ArraySource('second', ['key' => 'second']), priority: 5);

            $value = $resolver->get('key');

            expect($value)->toBe('first');
        });
    });

    describe('conditional sources', function (): void {
        test('skips sources that do not support the key', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('conditional', new CallbackSource(
                'conditional',
                fn(): string => 'conditional-value',
                fn(string $key): bool => $key === 'supported-key',
            ));
            $resolver->source('fallback', new ArraySource('fallback', ['unsupported-key' => 'fallback-value']));

            $value = $resolver->get('unsupported-key');

            expect($value)->toBe('fallback-value');
        });

        test('uses source that supports the key', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('conditional', new CallbackSource(
                'conditional',
                fn(): string => 'conditional-value',
                fn(string $key): bool => $key === 'supported-key',
            ));

            $value = $resolver->get('supported-key');

            expect($value)->toBe('conditional-value');
        });

        test('supports can use context for conditional logic', function (): void {
            $resolver = new Resolver('test');

            $resolver->source('prod-only', new CallbackSource(
                'prod-only',
                fn(): string => 'prod-value',
                fn(string $key, array $context): bool => ($context['env'] ?? null) === 'production',
            ));
            $resolver->source('fallback', new ArraySource('fallback', ['key' => 'fallback-value']));

            $prodValue = $resolver->get('key', context: ['env' => 'production']);
            $devValue = $resolver->get('key', context: ['env' => 'development']);

            expect($prodValue)->toBe('prod-value');
            expect($devValue)->toBe('fallback-value');
        });
    });

    describe('transformers', function (): void {
        test('applies transformer to resolved values', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('source', new ArraySource('source', ['key' => 'value']));
            $resolver->transform(fn($value) => strtoupper($value));

            $value = $resolver->get('key');

            expect($value)->toBe('VALUE');
        });

        test('applies multiple transformers in order', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('source', new ArraySource('source', ['key' => 'value']));
            $resolver->transform(fn($value) => strtoupper($value));
            $resolver->transform(fn($value): string|array => str_replace('A', '@', $value));

            $value = $resolver->get('key');

            expect($value)->toBe('V@LUE');
        });

        test('transformer receives source as second argument', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('source', new ArraySource('source', ['key' => 'value']));
            $resolver->transform(fn($value, $source): string => $value . ':' . $source->getName());

            $value = $resolver->get('key');

            expect($value)->toBe('value:source');
        });

        test('transformers are not applied when no value found', function (): void {
            $transformerCalled = false;

            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));
            $resolver->transform(function ($value) use (&$transformerCalled) {
                $transformerCalled = true;

                return $value;
            });

            $resolver->get('key');

            expect($transformerCalled)->toBeFalse();
        });
    });

    describe('resolve with metadata', function (): void {
        test('returns Result with value and source info', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('source', new ArraySource('source', ['key' => 'value']));

            $result = $resolver->resolve('key');

            expect($result)->toBeInstanceOf(Result::class);
            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe('value');
            expect($result->getSourceName())->toBe('source');
        });

        test('returns Result with attempted sources', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));
            $resolver->source('array', new ArraySource('array', ['key' => 'value']));

            $result = $resolver->resolve('key');

            expect($result->getAttemptedSources())->toBe(['null-source', 'array']);
        });

        test('attempted sources only includes sources that support the key', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('conditional', new CallbackSource(
                'conditional',
                fn(): string => 'value',
                fn(string $key): bool => $key === 'other-key',
            ));
            $resolver->source('array', new ArraySource('array', ['key' => 'value']));

            $result = $resolver->resolve('key');

            expect($result->getAttemptedSources())->toBe(['array']);
        });

        test('returns not found Result when no source has value', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));

            $result = $resolver->resolve('key');

            expect($result->wasFound())->toBeFalse();
            expect($result->getValue())->toBeNull();
            expect($result->getSourceName())->toBeNull();
        });
    });

    describe('getOrFail', function (): void {
        test('returns value when found', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('source', new ArraySource('source', ['key' => 'value']));

            $value = $resolver->getOrFail('key');

            expect($value)->toBe('value');
        });

        test('throws exception when not found', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null', new NullSource('null-source'));

            expect(fn(): mixed => $resolver->getOrFail('key'))
                ->toThrow(ResolutionFailedException::class);
        });

        test('exception includes attempted sources', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('null-1', new NullSource('null-1'));
            $resolver->source('null-2', new NullSource('null-2'));

            try {
                $resolver->getOrFail('key');
                expect(true)->toBeFalse('Expected exception to be thrown');
            } catch (ResolutionFailedException $resolutionFailedException) {
                expect($resolutionFailedException->getMessage())->toContain('null-1');
                expect($resolutionFailedException->getMessage())->toContain('null-2');
            }
        });
    });

    describe('helper methods', function (): void {
        test('fromCallback creates CallbackSource', function (): void {
            $resolver = new Resolver('test');
            $resolver->fromCallback('callback', fn(string $key): string => 'callback-value');

            $value = $resolver->get('key');

            expect($value)->toBe('callback-value');
        });

        test('fromCallback accepts supports callback', function (): void {
            $resolver = new Resolver('test');
            $resolver->fromCallback(
                'callback',
                fn(string $key): string => 'callback-value',
                fn(string $key): bool => $key === 'supported-key',
            );
            $resolver->fromArray('fallback', ['unsupported-key' => 'fallback-value']);

            $value = $resolver->get('unsupported-key');

            expect($value)->toBe('fallback-value');
        });

        test('fromCallback accepts priority', function (): void {
            $resolver = new Resolver('test');
            $resolver->fromCallback('low', fn(): string => 'low', priority: 10);
            $resolver->fromCallback('high', fn(): string => 'high', priority: 1);

            $value = $resolver->get('key');

            expect($value)->toBe('high');
        });

        test('fromArray creates ArraySource', function (): void {
            $resolver = new Resolver('test');
            $resolver->fromArray('array', ['key' => 'array-value']);

            $value = $resolver->get('key');

            expect($value)->toBe('array-value');
        });

        test('fromArray accepts priority', function (): void {
            $resolver = new Resolver('test');
            $resolver->fromArray('low', ['key' => 'low'], priority: 10);
            $resolver->fromArray('high', ['key' => 'high'], priority: 1);

            $value = $resolver->get('key');

            expect($value)->toBe('high');
        });
    });

    describe('resolver name', function (): void {
        test('returns resolver name', function (): void {
            $resolver = new Resolver('custom-resolver');

            expect($resolver->getName())->toBe('custom-resolver');
        });
    });

    describe('getSources', function (): void {
        test('returns sources in priority order', function (): void {
            $resolver = new Resolver('test');
            $resolver->source('low', new ArraySource('low', []), priority: 10);
            $resolver->source('high', new ArraySource('high', []), priority: 1);
            $resolver->source('medium', new ArraySource('medium', []), priority: 5);

            $sources = $resolver->getSources();

            expect($sources[0]->getName())->toBe('high');
            expect($sources[1]->getName())->toBe('medium');
            expect($sources[2]->getName())->toBe('low');
        });
    });
});
