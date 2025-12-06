<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Transform\CallbackTransformer;

describe('CallbackTransformer', function (): void {
    describe('constructor', function (): void {
        test('accepts callable closure', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): mixed => strtoupper((string) $value));

            expect($transformer)->toBeInstanceOf(CallbackTransformer::class);
        });

        test('accepts callable array', function (): void {
            $transformer = new CallbackTransformer([new class {
                public function transform(mixed $value): string
                {
                    return strtoupper((string) $value);
                }
            }, 'transform']);

            expect($transformer)->toBeInstanceOf(CallbackTransformer::class);
        });

        test('accepts invokable object', function (): void {
            $callable = new class {
                public function __invoke(mixed $value): string
                {
                    return strtoupper((string) $value);
                }
            };

            $transformer = new CallbackTransformer($callable);

            expect($transformer)->toBeInstanceOf(CallbackTransformer::class);
        });
    });

    describe('transform method', function (): void {
        test('transforms value using callback', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => strtoupper((string) $value));
            $source = new ArraySource('test-source', ['key' => 'value']);

            $result = $transformer->transform('hello', $source);

            expect($result)->toBe('HELLO');
        });

        test('receives both value and source parameters', function (): void {
            $receivedValue = null;
            $receivedSource = null;

            $transformer = new CallbackTransformer(function (mixed $value, $source) use (&$receivedValue, &$receivedSource): mixed {
                $receivedValue = $value;
                $receivedSource = $source;
                return $value;
            });

            $source = new ArraySource('test-source', ['key' => 'value']);
            $transformer->transform('test-value', $source);

            expect($receivedValue)->toBe('test-value');
            expect($receivedSource)->toBe($source);
        });

        test('can use source in transformation', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value, $source): string => $value . '-from-' . $source->getName()
            );
            $source = new ArraySource('custom-source', ['key' => 'value']);

            $result = $transformer->transform('data', $source);

            expect($result)->toBe('data-from-custom-source');
        });

        test('can access source data in transformation', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value, $source): string => $value . '-' . $source->get('prefix', [])
            );
            $source = new ArraySource('test-source', ['prefix' => 'transformed']);

            $result = $transformer->transform('value', $source);

            expect($result)->toBe('value-transformed');
        });
    });

    describe('value type handling', function (): void {
        test('transforms string values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => strtoupper((string) $value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform('hello world', $source);

            expect($result)->toBe('HELLO WORLD');
        });

        test('transforms integer values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): int => ((int) $value) * 2);
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(42, $source);

            expect($result)->toBe(84);
        });

        test('transforms float values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): float => round((float) $value, 2));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(3.14159, $source);

            expect($result)->toBe(3.14);
        });

        test('transforms boolean values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => $value ? 'yes' : 'no');
            $source = new ArraySource('test-source', []);

            $trueResult = $transformer->transform(true, $source);
            $falseResult = $transformer->transform(false, $source);

            expect($trueResult)->toBe('yes');
            expect($falseResult)->toBe('no');
        });

        test('transforms array values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): array => array_map(strtoupper(...), (array) $value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(['a', 'b', 'c'], $source);

            expect($result)->toBe(['A', 'B', 'C']);
        });

        test('transforms object values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): object => (object) ['wrapped' => $value]);
            $source = new ArraySource('test-source', []);

            $input = (object) ['key' => 'value'];
            $result = $transformer->transform($input, $source);

            expect($result)->toBeObject();
            expect($result->wrapped)->toBe($input);
        });

        test('transforms null values', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => $value === null ? 'null-value' : 'not-null');
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(null, $source);

            expect($result)->toBe('null-value');
        });

        test('can return null from transformation', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): ?string => null);
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform('anything', $source);

            expect($result)->toBeNull();
        });
    });

    describe('edge cases', function (): void {
        test('handles empty string', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): int => strlen((string) $value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform('', $source);

            expect($result)->toBe(0);
        });

        test('handles zero value', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): bool => $value === 0);
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(0, $source);

            expect($result)->toBeTrue();
        });

        test('handles false value', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => var_export($value, true));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(false, $source);

            expect($result)->toBe('false');
        });

        test('handles empty array', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): int => count((array) $value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform([], $source);

            expect($result)->toBe(0);
        });

        test('handles large arrays', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): int => count((array) $value));
            $source = new ArraySource('test-source', []);

            $largeArray = range(1, 10000);
            $result = $transformer->transform($largeArray, $source);

            expect($result)->toBe(10000);
        });

        test('handles nested arrays', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): array => array_map(
                    fn($item): array => is_array($item) ? array_map(strtoupper(...), $item) : [(string) $item],
                    (array) $value
                )
            );
            $source = new ArraySource('test-source', []);

            $nested = [
                ['a', 'b'],
                ['c', 'd'],
            ];
            $result = $transformer->transform($nested, $source);

            expect($result)->toBe([
                ['A', 'B'],
                ['C', 'D'],
            ]);
        });

        test('handles associative arrays', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): array => array_change_key_case((array) $value, CASE_UPPER)
            );
            $source = new ArraySource('test-source', []);

            $assoc = ['key1' => 'value1', 'key2' => 'value2'];
            $result = $transformer->transform($assoc, $source);

            expect($result)->toBe(['KEY1' => 'value1', 'KEY2' => 'value2']);
        });

        test('handles unicode strings', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => mb_strtoupper((string) $value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform('héllo wörld', $source);

            expect($result)->toBe('HÉLLO WÖRLD');
        });

        test('handles multibyte strings', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): int => mb_strlen((string) $value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform('こんにちは', $source);

            expect($result)->toBe(5);
        });

        test('handles special characters', function (): void {
            $transformer = new CallbackTransformer(fn(mixed $value): string => json_encode($value));
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform("line1\nline2\ttab", $source);

            expect($result)->toBe('"line1\nline2\ttab"');
        });
    });

    describe('complex transformations', function (): void {
        test('chains multiple operations', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): string => strtoupper(trim((string) $value))
            );
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform('  hello world  ', $source);

            expect($result)->toBe('HELLO WORLD');
        });

        test('performs conditional transformation', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): string|int => is_numeric($value) ? (int) $value * 2 : strtoupper((string) $value)
            );
            $source = new ArraySource('test-source', []);

            $numericResult = $transformer->transform('42', $source);
            $stringResult = $transformer->transform('hello', $source);

            expect($numericResult)->toBe(84);
            expect($stringResult)->toBe('HELLO');
        });

        test('performs type conversion', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): array => is_array($value) ? $value : [$value]
            );
            $source = new ArraySource('test-source', []);

            $arrayResult = $transformer->transform(['a', 'b'], $source);
            $scalarResult = $transformer->transform('single', $source);

            expect($arrayResult)->toBe(['a', 'b']);
            expect($scalarResult)->toBe(['single']);
        });

        test('extracts data from complex structures', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): array => array_column((array) $value, 'name')
            );
            $source = new ArraySource('test-source', []);

            $data = [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
                ['id' => 3, 'name' => 'Charlie'],
            ];
            $result = $transformer->transform($data, $source);

            expect($result)->toBe(['Alice', 'Bob', 'Charlie']);
        });

        test('filters and maps array', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value): array => array_map(
                    strtoupper(...),
                    array_filter(
                        (array) $value,
                        fn($item): bool => strlen((string) $item) > 2
                    )
                )
            );
            $source = new ArraySource('test-source', []);

            $result = $transformer->transform(['a', 'abc', 'ab', 'abcd'], $source);

            expect($result)->toBe([1 => 'ABC', 3 => 'ABCD']);
        });

        test('combines value with source data', function (): void {
            $transformer = new CallbackTransformer(
                fn(mixed $value, $source): string => sprintf(
                    '%s:%s:%s',
                    $source->getName(),
                    $source->get('prefix', []),
                    $value
                )
            );
            $source = new ArraySource('my-source', ['prefix' => 'PRE']);

            $result = $transformer->transform('data', $source);

            expect($result)->toBe('my-source:PRE:data');
        });
    });

    describe('readonly behavior', function (): void {
        test('CallbackTransformer is readonly', function (): void {
            $reflection = new ReflectionClass(CallbackTransformer::class);

            expect($reflection->isReadOnly())->toBeTrue();
        });

        test('CallbackTransformer is final', function (): void {
            $reflection = new ReflectionClass(CallbackTransformer::class);

            expect($reflection->isFinal())->toBeTrue();
        });
    });
});
