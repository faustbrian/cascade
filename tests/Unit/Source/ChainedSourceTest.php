<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\ChainedSource;
use Cline\Cascade\Source\NullSource;

describe('ChainedSource', function (): void {
    describe('basic chaining', function (): void {
        test('resolves from first source when available', function (): void {
            $source1 = new ArraySource('source-1', ['key' => 'value-1']);
            $source2 = new ArraySource('source-2', ['key' => 'value-2']);

            $chained = new ChainedSource('chained', [$source1, $source2]);

            $value = $chained->get('key', []);

            expect($value)->toBe('value-1');
        });

        test('falls back to second source when first returns null', function (): void {
            $source1 = new NullSource('null-source');
            $source2 = new ArraySource('source-2', ['key' => 'value-2']);

            $chained = new ChainedSource('chained', [$source1, $source2]);

            $value = $chained->get('key', []);

            expect($value)->toBe('value-2');
        });

        test('returns null when all sources return null', function (): void {
            $source1 = new NullSource('null-1');
            $source2 = new NullSource('null-2');
            $source3 = new NullSource('null-3');

            $chained = new ChainedSource('chained', [$source1, $source2, $source3]);

            $value = $chained->get('key', []);

            expect($value)->toBeNull();
        });

        test('tries sources in order until value found', function (): void {
            $source1 = new NullSource('null-1');
            $source2 = new NullSource('null-2');
            $source3 = new ArraySource('source-3', ['key' => 'value-3']);
            $source4 = new ArraySource('source-4', ['key' => 'value-4']);

            $chained = new ChainedSource('chained', [$source1, $source2, $source3, $source4]);

            $value = $chained->get('key', []);

            expect($value)->toBe('value-3');
        });
    });

    describe('supports behavior', function (): void {
        test('supports key if any chained source supports it', function (): void {
            $source1 = new CallbackSource(
                'conditional-1',
                fn (): string => 'value',
                fn (string $key): bool => $key === 'key-1',
            );
            $source2 = new CallbackSource(
                'conditional-2',
                fn (): string => 'value',
                fn (string $key): bool => $key === 'key-2',
            );

            $chained = new ChainedSource('chained', [$source1, $source2]);

            expect($chained->supports('key-1', []))->toBeTrue();
            expect($chained->supports('key-2', []))->toBeTrue();
            expect($chained->supports('key-3', []))->toBeFalse();
        });

        test('skips sources that do not support the key', function (): void {
            $source1 = new CallbackSource(
                'conditional',
                fn (): string => 'value-1',
                fn (string $key): bool => $key === 'other-key',
            );
            $source2 = new ArraySource('fallback', ['key' => 'value-2']);

            $chained = new ChainedSource('chained', [$source1, $source2]);

            $value = $chained->get('key', []);

            expect($value)->toBe('value-2');
        });

        test('supports with context', function (): void {
            $source1 = new CallbackSource(
                'env-specific',
                fn (): string => 'value',
                fn (string $key, array $context): bool => ($context['env'] ?? null) === 'production',
            );
            $source2 = new ArraySource('default', ['key' => 'default-value']);

            $chained = new ChainedSource('chained', [$source1, $source2]);

            expect($chained->supports('key', ['env' => 'production']))->toBeTrue();
            expect($chained->supports('key', ['env' => 'development']))->toBeTrue(); // Supported by source2
            expect($chained->supports('non-existent', []))->toBeTrue(); // Supported by source2 (always true)
        });
    });

    describe('context propagation', function (): void {
        test('passes context to all chained sources', function (): void {
            $receivedContexts = [];

            $source1 = new CallbackSource('callback-1', function (string $key, array $context) use (&$receivedContexts): null {
                $receivedContexts[] = $context;

                return null;
            });

            $source2 = new CallbackSource('callback-2', function (string $key, array $context) use (&$receivedContexts): string {
                $receivedContexts[] = $context;

                return 'value';
            });

            $chained = new ChainedSource('chained', [$source1, $source2]);

            $chained->get('key', ['env' => 'production']);

            expect($receivedContexts)->toHaveCount(2);
            expect($receivedContexts[0])->toBe(['env' => 'production']);
            expect($receivedContexts[1])->toBe(['env' => 'production']);
        });

        test('context affects which source provides value', function (): void {
            $source1 = new CallbackSource(
                'prod-source',
                fn (): string => 'prod-value',
                fn (string $key, array $context): bool => ($context['env'] ?? null) === 'production',
            );

            $source2 = new CallbackSource(
                'dev-source',
                fn (): string => 'dev-value',
                fn (string $key, array $context): bool => ($context['env'] ?? null) === 'development',
            );

            $chained = new ChainedSource('chained', [$source1, $source2]);

            $prodValue = $chained->get('key', ['env' => 'production']);
            $devValue = $chained->get('key', ['env' => 'development']);

            expect($prodValue)->toBe('prod-value');
            expect($devValue)->toBe('dev-value');
        });
    });

    describe('name and metadata', function (): void {
        test('returns source name', function (): void {
            $chained = new ChainedSource('custom-name', []);

            expect($chained->getName())->toBe('custom-name');
        });

        test('metadata includes name and type', function (): void {
            $chained = new ChainedSource('chained', []);

            $metadata = $chained->getMetadata();

            expect($metadata['name'])->toBe('chained');
            expect($metadata['type'])->toBe('chained');
        });

        test('metadata includes source count', function (): void {
            $source1 = new NullSource('null-1');
            $source2 = new NullSource('null-2');
            $source3 = new NullSource('null-3');

            $chained = new ChainedSource('chained', [$source1, $source2, $source3]);

            $metadata = $chained->getMetadata();

            expect($metadata['source_count'])->toBe(3);
        });

        test('metadata includes source names', function (): void {
            $source1 = new ArraySource('array', []);
            $source2 = new NullSource('null');
            $source3 = new CallbackSource('callback', fn (): null => null);

            $chained = new ChainedSource('chained', [$source1, $source2, $source3]);

            $metadata = $chained->getMetadata();

            expect($metadata['sources'])->toBe(['array', 'null', 'callback']);
        });

        test('metadata for empty chain', function (): void {
            $chained = new ChainedSource('empty', []);

            $metadata = $chained->getMetadata();

            expect($metadata['source_count'])->toBe(0);
            expect($metadata['sources'])->toBe([]);
        });
    });

    describe('nested chaining', function (): void {
        test('chains can be nested', function (): void {
            $innerChain = new ChainedSource('inner', [
                new NullSource('null-1'),
                new ArraySource('array', ['key' => 'inner-value']),
            ]);

            $outerChain = new ChainedSource('outer', [
                new NullSource('null-2'),
                $innerChain,
            ]);

            $value = $outerChain->get('key', []);

            expect($value)->toBe('inner-value');
        });

        test('multiple levels of nesting', function (): void {
            $level3 = new ChainedSource('level-3', [
                new ArraySource('deep', ['key' => 'deep-value']),
            ]);

            $level2 = new ChainedSource('level-2', [
                new NullSource('null-2'),
                $level3,
            ]);

            $level1 = new ChainedSource('level-1', [
                new NullSource('null-1'),
                $level2,
            ]);

            $value = $level1->get('key', []);

            expect($value)->toBe('deep-value');
        });
    });

    describe('use cases', function (): void {
        test('configuration hierarchy (user > project > defaults)', function (): void {
            $userConfig = new ArraySource('user', ['theme' => 'dark']);
            $projectConfig = new ArraySource('project', ['theme' => 'light', 'language' => 'en']);
            $defaults = new ArraySource('defaults', ['theme' => 'system', 'language' => 'en', 'notifications' => true]);

            $config = new ChainedSource('config', [$userConfig, $projectConfig, $defaults]);

            expect($config->get('theme', []))->toBe('dark'); // From user
            expect($config->get('language', []))->toBe('en'); // From project
            expect($config->get('notifications', []))->toBe(true); // From defaults
        });

        test('environment-specific configuration fallback', function (): void {
            $production = new CallbackSource(
                'production',
                fn (string $key): ?string => match ($key) {
                    'db.host' => 'prod.db.com',
                    default => null,
                },
                fn (string $key, array $context): bool => ($context['env'] ?? null) === 'production',
            );

            $development = new CallbackSource(
                'development',
                fn (string $key): string|true|null => match ($key) {
                    'db.host' => 'localhost',
                    'debug' => true,
                    default => null,
                },
                fn (string $key, array $context): bool => ($context['env'] ?? null) === 'development',
            );

            $defaults = new ArraySource('defaults', [
                'db.host' => 'default.db.com',
                'db.port' => 3_306,
                'debug' => false,
            ]);

            $config = new ChainedSource('env-config', [$production, $development, $defaults]);

            // Production context
            expect($config->get('db.host', ['env' => 'production']))->toBe('prod.db.com');
            expect($config->get('db.port', ['env' => 'production']))->toBe(3_306);
            expect($config->get('debug', ['env' => 'production']))->toBe(false);

            // Development context
            expect($config->get('db.host', ['env' => 'development']))->toBe('localhost');
            expect($config->get('debug', ['env' => 'development']))->toBe(true);
        });

        test('feature flag system with overrides', function (): void {
            $userOverrides = new ArraySource('user-overrides', ['new_ui' => false]);
            $betaFeatures = new ArraySource('beta', ['experimental_api' => true, 'new_ui' => true]);
            $stableFeatures = new ArraySource('stable', ['basic_features' => true]);

            $features = new ChainedSource('features', [$userOverrides, $betaFeatures, $stableFeatures]);

            expect($features->get('new_ui', []))->toBe(false); // User override
            expect($features->get('experimental_api', []))->toBe(true); // Beta
            expect($features->get('basic_features', []))->toBe(true); // Stable
        });

        test('localization with fallback chain', function (): void {
            $regional = new ArraySource('es-MX', ['greeting' => 'Hola México']);
            $language = new ArraySource('es', ['greeting' => 'Hola', 'farewell' => 'Adiós']);
            $default = new ArraySource('en', ['greeting' => 'Hello', 'farewell' => 'Goodbye', 'welcome' => 'Welcome']);

            $i18n = new ChainedSource('i18n', [$regional, $language, $default]);

            expect($i18n->get('greeting', []))->toBe('Hola México'); // Regional
            expect($i18n->get('farewell', []))->toBe('Adiós'); // Language
            expect($i18n->get('welcome', []))->toBe('Welcome'); // Default
        });
    });

    describe('edge cases', function (): void {
        test('empty source array returns null', function (): void {
            $chained = new ChainedSource('empty', []);

            $value = $chained->get('key', []);

            expect($value)->toBeNull();
        });

        test('single source chain behaves like unwrapped source', function (): void {
            $source = new ArraySource('single', ['key' => 'value']);
            $chained = new ChainedSource('chained', [$source]);

            $value = $chained->get('key', []);

            expect($value)->toBe('value');
        });

        test('handles falsy values correctly', function (): void {
            $source1 = new ArraySource('source-1', ['zero' => 0, 'empty' => '', 'false' => false]);
            $source2 = new ArraySource('source-2', ['zero' => 1, 'empty' => 'not-empty', 'false' => true]);

            $chained = new ChainedSource('chained', [$source1, $source2]);

            // Should get falsy values from source1, not fall back to source2
            expect($chained->get('zero', []))->toBe(0);
            expect($chained->get('empty', []))->toBe('');
            expect($chained->get('false', []))->toBe(false);
        });

        test('stops at first non-null value', function (): void {
            $callCounts = [0, 0, 0];

            $source1 = new CallbackSource('callback-1', function () use (&$callCounts): null {
                ++$callCounts[0];

                return null;
            });

            $source2 = new CallbackSource('callback-2', function () use (&$callCounts): string {
                ++$callCounts[1];

                return 'value';
            });

            $source3 = new CallbackSource('callback-3', function () use (&$callCounts): string {
                ++$callCounts[2];

                return 'other-value';
            });

            $chained = new ChainedSource('chained', [$source1, $source2, $source3]);

            $chained->get('key', []);

            expect($callCounts)->toBe([1, 1, 0]); // source3 never called
        });
    });
});
