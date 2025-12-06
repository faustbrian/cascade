<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Source\ArraySource;

describe('ArraySource', function (): void {
    describe('basic resolution', function (): void {
        test('resolves value from array', function (): void {
            $source = new ArraySource('config', [
                'app.name' => 'My Application',
                'app.version' => '1.0.0',
            ]);

            $value = $source->get('app.name', []);

            expect($value)->toBe('My Application');
        });

        test('returns null for non-existent key', function (): void {
            $source = new ArraySource('config', [
                'app.name' => 'My Application',
            ]);

            $value = $source->get('non.existent', []);

            expect($value)->toBeNull();
        });

        test('context parameter is ignored', function (): void {
            $source = new ArraySource('config', [
                'key' => 'value',
            ]);

            $withContext = $source->get('key', ['env' => 'production']);
            $withoutContext = $source->get('key', []);

            expect($withContext)->toBe('value');
            expect($withoutContext)->toBe('value');
        });

        test('resolves multiple keys from same source', function (): void {
            $source = new ArraySource('config', [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3',
            ]);

            expect($source->get('key1', []))->toBe('value1');
            expect($source->get('key2', []))->toBe('value2');
            expect($source->get('key3', []))->toBe('value3');
        });
    });

    describe('supports', function (): void {
        test('supports all keys by default', function (): void {
            $source = new ArraySource('config', [
                'existing' => 'value',
            ]);

            expect($source->supports('existing', []))->toBeTrue();
            expect($source->supports('non-existing', []))->toBeTrue();
            expect($source->supports('any-key', []))->toBeTrue();
        });

        test('supports method ignores context', function (): void {
            $source = new ArraySource('config', ['key' => 'value']);

            expect($source->supports('key', []))->toBeTrue();
            expect($source->supports('key', ['env' => 'production']))->toBeTrue();
        });
    });

    describe('name and metadata', function (): void {
        test('returns source name', function (): void {
            $source = new ArraySource('custom-name', []);

            expect($source->getName())->toBe('custom-name');
        });

        test('metadata includes name and type', function (): void {
            $source = new ArraySource('config', ['key' => 'value']);

            $metadata = $source->getMetadata();

            expect($metadata['name'])->toBe('config');
            expect($metadata['type'])->toBe('array');
        });

        test('metadata includes key count', function (): void {
            $source = new ArraySource('config', [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3',
            ]);

            $metadata = $source->getMetadata();

            expect($metadata['key_count'])->toBe(3);
        });

        test('metadata includes all keys', function (): void {
            $source = new ArraySource('config', [
                'app.name' => 'My App',
                'app.version' => '1.0.0',
                'db.host' => 'localhost',
            ]);

            $metadata = $source->getMetadata();

            expect($metadata['keys'])->toBe(['app.name', 'app.version', 'db.host']);
        });

        test('metadata for empty source', function (): void {
            $source = new ArraySource('empty', []);

            $metadata = $source->getMetadata();

            expect($metadata['key_count'])->toBe(0);
            expect($metadata['keys'])->toBe([]);
        });
    });

    describe('value types', function (): void {
        test('stores and retrieves string values', function (): void {
            $source = new ArraySource('config', ['key' => 'string value']);

            expect($source->get('key', []))->toBe('string value');
        });

        test('stores and retrieves integer values', function (): void {
            $source = new ArraySource('config', ['port' => 8080]);

            expect($source->get('port', []))->toBe(8080);
        });

        test('stores and retrieves boolean values', function (): void {
            $source = new ArraySource('config', [
                'debug' => true,
                'cache' => false,
            ]);

            expect($source->get('debug', []))->toBe(true);
            expect($source->get('cache', []))->toBe(false);
        });

        test('stores and retrieves array values', function (): void {
            $source = new ArraySource('config', [
                'database' => [
                    'host' => 'localhost',
                    'port' => 3306,
                ],
            ]);

            expect($source->get('database', []))->toBe([
                'host' => 'localhost',
                'port' => 3306,
            ]);
        });

        test('stores and retrieves object values', function (): void {
            $object = (object) ['key' => 'value'];
            $source = new ArraySource('config', ['object' => $object]);

            expect($source->get('object', []))->toBe($object);
        });

        test('stores and retrieves null explicitly', function (): void {
            $source = new ArraySource('config', ['nullable' => null]);

            // Explicitly null value returns null (same as non-existent key)
            expect($source->get('nullable', []))->toBeNull();
        });

        test('distinguishes between non-existent and null values', function (): void {
            $source = new ArraySource('config', ['explicit_null' => null]);

            // Both return null, but for different reasons
            expect($source->get('explicit_null', []))->toBeNull();
            expect($source->get('non_existent', []))->toBeNull();

            // Check via metadata that explicit_null key exists
            $metadata = $source->getMetadata();
            expect($metadata['keys'])->toContain('explicit_null');
            expect($metadata['keys'])->not->toContain('non_existent');
        });
    });

    describe('edge cases', function (): void {
        test('handles empty array', function (): void {
            $source = new ArraySource('empty', []);

            expect($source->get('any-key', []))->toBeNull();
        });

        test('handles empty string key', function (): void {
            $source = new ArraySource('config', ['' => 'empty-key-value']);

            expect($source->get('', []))->toBe('empty-key-value');
        });

        test('handles numeric string keys', function (): void {
            $source = new ArraySource('config', [
                '0' => 'zero',
                '1' => 'one',
                '42' => 'forty-two',
            ]);

            expect($source->get('0', []))->toBe('zero');
            expect($source->get('42', []))->toBe('forty-two');
        });

        test('handles special characters in keys', function (): void {
            $source = new ArraySource('config', [
                'key.with.dots' => 'value1',
                'key-with-dashes' => 'value2',
                'key_with_underscores' => 'value3',
                'key:with:colons' => 'value4',
            ]);

            expect($source->get('key.with.dots', []))->toBe('value1');
            expect($source->get('key-with-dashes', []))->toBe('value2');
            expect($source->get('key_with_underscores', []))->toBe('value3');
            expect($source->get('key:with:colons', []))->toBe('value4');
        });

        test('handles zero as value', function (): void {
            $source = new ArraySource('config', ['zero' => 0]);

            expect($source->get('zero', []))->toBe(0);
        });

        test('handles empty string as value', function (): void {
            $source = new ArraySource('config', ['empty' => '']);

            expect($source->get('empty', []))->toBe('');
        });

        test('handles false as value', function (): void {
            $source = new ArraySource('config', ['false' => false]);

            expect($source->get('false', []))->toBe(false);
        });

        test('handles large array', function (): void {
            $values = [];
            for ($i = 0; $i < 1000; ++$i) {
                $values['key' . $i] = 'value' . $i;
            }

            $source = new ArraySource('large', $values);

            expect($source->get('key0', []))->toBe('value0');
            expect($source->get('key500', []))->toBe('value500');
            expect($source->get('key999', []))->toBe('value999');
            expect($source->getMetadata()['key_count'])->toBe(1000);
        });
    });

    describe('use cases', function (): void {
        test('application configuration', function (): void {
            $source = new ArraySource('app-config', [
                'app.name' => 'My Application',
                'app.version' => '1.0.0',
                'app.debug' => true,
                'app.timezone' => 'UTC',
            ]);

            expect($source->get('app.name', []))->toBe('My Application');
            expect($source->get('app.debug', []))->toBe(true);
        });

        test('default values source', function (): void {
            $source = new ArraySource('defaults', [
                'timeout' => 30,
                'retries' => 3,
                'cache_ttl' => 3600,
            ]);

            expect($source->get('timeout', []))->toBe(30);
            expect($source->get('retries', []))->toBe(3);
            expect($source->get('cache_ttl', []))->toBe(3600);
        });

        test('feature flags', function (): void {
            $source = new ArraySource('features', [
                'new_ui' => true,
                'beta_features' => false,
                'experimental_api' => true,
            ]);

            expect($source->get('new_ui', []))->toBe(true);
            expect($source->get('beta_features', []))->toBe(false);
            expect($source->get('experimental_api', []))->toBe(true);
            expect($source->get('non_existent_feature', []))->toBeNull();
        });

        test('translation strings', function (): void {
            $source = new ArraySource('translations', [
                'welcome' => 'Welcome!',
                'goodbye' => 'Goodbye!',
                'error.not_found' => 'Not found',
                'error.unauthorized' => 'Unauthorized',
            ]);

            expect($source->get('welcome', []))->toBe('Welcome!');
            expect($source->get('error.not_found', []))->toBe('Not found');
        });
    });
});
