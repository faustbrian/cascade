<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Source\NullSource;

describe('NullSource', function (): void {
    describe('basic behavior', function (): void {
        test('always returns null', function (): void {
            $source = new NullSource('null-source');

            $value = $source->get('any-key', []);

            expect($value)->toBeNull();
        });

        test('returns null for multiple different keys', function (): void {
            $source = new NullSource('null-source');

            expect($source->get('key1', []))->toBeNull();
            expect($source->get('key2', []))->toBeNull();
            expect($source->get('key3', []))->toBeNull();
        });

        test('returns null regardless of context', function (): void {
            $source = new NullSource('null-source');

            expect($source->get('key', []))->toBeNull();
            expect($source->get('key', ['env' => 'production']))->toBeNull();
            expect($source->get('key', ['any' => 'context', 'values' => 'here']))->toBeNull();
        });
    });

    describe('supports behavior', function (): void {
        test('supports all keys', function (): void {
            $source = new NullSource('null-source');

            expect($source->supports('any-key', []))->toBeTrue();
            expect($source->supports('other-key', []))->toBeTrue();
            expect($source->supports('', []))->toBeTrue();
        });

        test('supports with any context', function (): void {
            $source = new NullSource('null-source');

            expect($source->supports('key', []))->toBeTrue();
            expect($source->supports('key', ['env' => 'production']))->toBeTrue();
            expect($source->supports('key', ['any' => 'context']))->toBeTrue();
        });
    });

    describe('name and metadata', function (): void {
        test('returns source name', function (): void {
            $source = new NullSource('custom-name');

            expect($source->getName())->toBe('custom-name');
        });

        test('metadata includes name and type', function (): void {
            $source = new NullSource('null-source');

            $metadata = $source->getMetadata();

            expect($metadata['name'])->toBe('null-source');
            expect($metadata['type'])->toBe('null');
        });

        test('different instances can have different names', function (): void {
            $source1 = new NullSource('null-1');
            $source2 = new NullSource('null-2');

            expect($source1->getName())->toBe('null-1');
            expect($source2->getName())->toBe('null-2');
        });
    });

    describe('use cases', function (): void {
        test('testing fallback behavior in resolvers', function (): void {
            $source = new NullSource('null-source');

            // Useful for testing that resolvers properly fall back to next source
            $value = $source->get('key', []);

            expect($value)->toBeNull();
        });

        test('placeholder source in chains', function (): void {
            $source = new NullSource('placeholder');

            // Can be used as a placeholder that will be replaced later
            expect($source->get('config.key', []))->toBeNull();
        });

        test('disabled source simulation', function (): void {
            $source = new NullSource('disabled-feature');

            // Simulates a disabled feature or source
            expect($source->get('feature.enabled', []))->toBeNull();
        });

        test('multiple null sources with different names for tracking', function (): void {
            $null1 = new NullSource('cache-miss');
            $null2 = new NullSource('database-unavailable');
            $null3 = new NullSource('api-timeout');

            expect($null1->getName())->toBe('cache-miss');
            expect($null2->getName())->toBe('database-unavailable');
            expect($null3->getName())->toBe('api-timeout');

            expect($null1->get('key', []))->toBeNull();
            expect($null2->get('key', []))->toBeNull();
            expect($null3->get('key', []))->toBeNull();
        });
    });

    describe('edge cases', function (): void {
        test('handles empty string key', function (): void {
            $source = new NullSource('null-source');

            expect($source->get('', []))->toBeNull();
        });

        test('handles empty context', function (): void {
            $source = new NullSource('null-source');

            expect($source->get('key', []))->toBeNull();
        });

        test('consistent behavior across many calls', function (): void {
            $source = new NullSource('null-source');

            for ($i = 0; $i < 100; ++$i) {
                expect($source->get('key-' . $i, []))->toBeNull();
            }
        });

        test('metadata remains constant', function (): void {
            $source = new NullSource('null-source');

            $metadata1 = $source->getMetadata();
            $source->get('key1', []);
            $source->get('key2', []);

            $metadata2 = $source->getMetadata();

            expect($metadata1)->toBe($metadata2);
        });
    });

    describe('comparison with other sources', function (): void {
        test('differs from source that returns empty string', function (): void {
            $nullSource = new NullSource('null');

            // NullSource returns null, not empty string
            $value = $nullSource->get('key', []);

            expect($value)->toBeNull();
            expect($value)->not->toBe('');
        });

        test('differs from source that returns false', function (): void {
            $nullSource = new NullSource('null');

            $value = $nullSource->get('key', []);

            expect($value)->toBeNull();
            expect($value)->not->toBe(false);
        });

        test('differs from source that returns zero', function (): void {
            $nullSource = new NullSource('null');

            $value = $nullSource->get('key', []);

            expect($value)->toBeNull();
            expect($value)->not->toBe(0);
        });
    });
});
