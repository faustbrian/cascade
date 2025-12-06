<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Illuminate\Support\Facades\Date;
use Cline\Cascade\Result;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\NullSource;

describe('Result', function (): void {
    describe('found results', function (): void {
        test('creates found result with value', function (): void {
            $source = new ArraySource('test-source', ['key' => 'value']);
            $result = Result::found(
                value: 'test-value',
                source: $source,
                attempted: ['test-source'],
            );

            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe('test-value');
        });

        test('stores source information', function (): void {
            $source = new ArraySource('test-source', ['key' => 'value']);
            $result = Result::found(
                value: 'test-value',
                source: $source,
                attempted: ['test-source'],
            );

            expect($result->getSource())->toBe($source);
            expect($result->getSourceName())->toBe('test-source');
        });

        test('stores attempted sources', function (): void {
            $source = new ArraySource('source-2', ['key' => 'value']);
            $result = Result::found(
                value: 'test-value',
                source: $source,
                attempted: ['source-1', 'source-2'],
            );

            expect($result->getAttemptedSources())->toBe(['source-1', 'source-2']);
        });

        test('stores metadata', function (): void {
            $source = new ArraySource('test-source', ['key' => 'value']);
            $metadata = ['cache_hit' => true, 'ttl' => 300];

            $result = Result::found(
                value: 'test-value',
                source: $source,
                attempted: ['test-source'],
                metadata: $metadata,
            );

            expect($result->getMetadata())->toBe($metadata);
        });

        test('metadata defaults to empty array', function (): void {
            $source = new ArraySource('test-source', ['key' => 'value']);
            $result = Result::found(
                value: 'test-value',
                source: $source,
                attempted: ['test-source'],
            );

            expect($result->getMetadata())->toBe([]);
        });

        test('can store null as value', function (): void {
            $source = new NullSource('null-source');
            $result = Result::found(
                value: null,
                source: $source,
                attempted: ['null-source'],
            );

            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBeNull();
        });

        test('can store various value types', function (): void {
            $source = new ArraySource('test-source', []);

            $stringResult = Result::found('string', $source, ['test-source']);
            expect($stringResult->getValue())->toBe('string');

            $intResult = Result::found(42, $source, ['test-source']);
            expect($intResult->getValue())->toBe(42);

            $arrayResult = Result::found(['key' => 'value'], $source, ['test-source']);
            expect($arrayResult->getValue())->toBe(['key' => 'value']);

            $objectResult = Result::found((object) ['key' => 'value'], $source, ['test-source']);
            expect($objectResult->getValue())->toEqual((object) ['key' => 'value']);

            $boolResult = Result::found(true, $source, ['test-source']);
            expect($boolResult->getValue())->toBe(true);
        });
    });

    describe('not found results', function (): void {
        test('creates not found result', function (): void {
            $result = Result::notFound(['source-1', 'source-2']);

            expect($result->wasFound())->toBeFalse();
            expect($result->getValue())->toBeNull();
        });

        test('stores attempted sources', function (): void {
            $result = Result::notFound(['source-1', 'source-2', 'source-3']);

            expect($result->getAttemptedSources())->toBe(['source-1', 'source-2', 'source-3']);
        });

        test('has no source information', function (): void {
            $result = Result::notFound(['source-1']);

            expect($result->getSource())->toBeNull();
            expect($result->getSourceName())->toBeNull();
        });

        test('has empty metadata', function (): void {
            $result = Result::notFound(['source-1']);

            expect($result->getMetadata())->toBe([]);
        });

        test('can be created with empty attempted sources', function (): void {
            $result = Result::notFound([]);

            expect($result->wasFound())->toBeFalse();
            expect($result->getAttemptedSources())->toBe([]);
        });
    });

    describe('readonly behavior', function (): void {
        test('Result is readonly', function (): void {
            $source = new ArraySource('test-source', ['key' => 'value']);
            $result = Result::found('value', $source, ['test-source']);

            // Reflection to verify readonly
            $reflection = new ReflectionClass(Result::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });
    });

    describe('edge cases', function (): void {
        test('handles empty string as value', function (): void {
            $source = new ArraySource('test-source', []);
            $result = Result::found('', $source, ['test-source']);

            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe('');
        });

        test('handles zero as value', function (): void {
            $source = new ArraySource('test-source', []);
            $result = Result::found(0, $source, ['test-source']);

            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe(0);
        });

        test('handles false as value', function (): void {
            $source = new ArraySource('test-source', []);
            $result = Result::found(false, $source, ['test-source']);

            expect($result->wasFound())->toBeTrue();
            expect($result->getValue())->toBe(false);
        });

        test('handles large attempted sources list', function (): void {
            $sources = array_map(fn($i): string => 'source-' . $i, range(1, 100));
            $result = Result::notFound($sources);

            expect($result->getAttemptedSources())->toHaveCount(100);
        });

        test('handles complex metadata', function (): void {
            $source = new ArraySource('test-source', []);
            $metadata = [
                'cache_hit' => true,
                'ttl' => 300,
                'nested' => [
                    'level1' => [
                        'level2' => 'value',
                    ],
                ],
                'timestamp' => Date::now()->getTimestamp(),
            ];

            $result = Result::found('value', $source, ['test-source'], $metadata);

            expect($result->getMetadata())->toBe($metadata);
        });
    });
});
