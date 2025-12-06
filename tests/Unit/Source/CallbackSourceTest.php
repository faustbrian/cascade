<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Source\CallbackSource;

describe('CallbackSource', function (): void {
    describe('basic resolution', function (): void {
        test('resolves value using callback', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(string $key): string => 'value-for-' . $key,
            );

            $value = $source->get('test-key', []);

            expect($value)->toBe('value-for-test-key');
        });

        test('returns null when callback returns null', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): null => null,
            );

            $value = $source->get('key', []);

            expect($value)->toBeNull();
        });

        test('passes key to callback', function (): void {
            $receivedKey = null;

            $source = new CallbackSource(
                'callback',
                function (string $key) use (&$receivedKey): string {
                    $receivedKey = $key;

                    return 'value';
                },
            );

            $source->get('test-key', []);

            expect($receivedKey)->toBe('test-key');
        });

        test('passes context to callback', function (): void {
            $receivedContext = null;

            $source = new CallbackSource(
                'callback',
                function (string $key, array $context) use (&$receivedContext): string {
                    $receivedContext = $context;

                    return 'value';
                },
            );

            $source->get('key', ['env' => 'production']);

            expect($receivedContext)->toBe(['env' => 'production']);
        });

        test('can use context in resolution logic', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(string $key, array $context): string => $context['prefix'] . $key,
            );

            $value = $source->get('key', ['prefix' => 'prod-']);

            expect($value)->toBe('prod-key');
        });
    });

    describe('supports callback', function (): void {
        test('supports all keys when supports callback not provided', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'value',
            );

            expect($source->supports('any-key', []))->toBeTrue();
            expect($source->supports('other-key', []))->toBeTrue();
        });

        test('uses supports callback to filter keys', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'value',
                fn(string $key): bool => str_starts_with($key, 'app.'),
            );

            expect($source->supports('app.name', []))->toBeTrue();
            expect($source->supports('db.host', []))->toBeFalse();
        });

        test('supports callback receives context', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'value',
                fn(string $key, array $context): bool => ($context['enabled'] ?? false) === true,
            );

            expect($source->supports('key', ['enabled' => true]))->toBeTrue();
            expect($source->supports('key', ['enabled' => false]))->toBeFalse();
            expect($source->supports('key', []))->toBeFalse();
        });

        test('supports callback can combine key and context', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'value',
                fn(string $key, array $context): bool => str_starts_with($key, 'app.') && $context['env'] === 'production',
            );

            expect($source->supports('app.name', ['env' => 'production']))->toBeTrue();
            expect($source->supports('app.name', ['env' => 'development']))->toBeFalse();
            expect($source->supports('db.host', ['env' => 'production']))->toBeFalse();
        });
    });

    describe('transformer callback', function (): void {
        test('applies transformer to non-null values', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'value',
                transformer: fn($value) => strtoupper($value),
            );

            $value = $source->get('key', []);

            expect($value)->toBe('VALUE');
        });

        test('does not apply transformer to null values', function (): void {
            $transformerCalled = false;

            $source = new CallbackSource(
                'callback',
                fn(): null => null,
                transformer: function ($value) use (&$transformerCalled) {
                    $transformerCalled = true;

                    return $value;
                },
            );

            $source->get('key', []);

            expect($transformerCalled)->toBeFalse();
        });

        test('transformer can return different type', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => '42',
                transformer: fn($value): int => (int) $value,
            );

            $value = $source->get('key', []);

            expect($value)->toBe(42);
        });

        test('transformer can return null', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'value',
                transformer: fn(): null => null,
            );

            $value = $source->get('key', []);

            expect($value)->toBeNull();
        });

        test('multiple transformations by chaining', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(): string => 'hello',
                transformer: fn($value) => strtoupper(str_replace('l', '1', $value)),
            );

            $value = $source->get('key', []);

            expect($value)->toBe('HE11O');
        });
    });

    describe('name and metadata', function (): void {
        test('returns source name', function (): void {
            $source = new CallbackSource('custom-name', fn(): string => 'value');

            expect($source->getName())->toBe('custom-name');
        });

        test('metadata includes name and type', function (): void {
            $source = new CallbackSource('callback', fn(): string => 'value');

            $metadata = $source->getMetadata();

            expect($metadata['name'])->toBe('callback');
            expect($metadata['type'])->toBe('callback');
        });

        test('metadata indicates if supports callback provided', function (): void {
            $withSupports = new CallbackSource(
                'callback',
                fn(): string => 'value',
                fn(): true => true,
            );
            $withoutSupports = new CallbackSource(
                'callback',
                fn(): string => 'value',
            );

            expect($withSupports->getMetadata()['has_supports'])->toBeTrue();
            expect($withoutSupports->getMetadata()['has_supports'])->toBeFalse();
        });

        test('metadata indicates if transformer provided', function (): void {
            $withTransformer = new CallbackSource(
                'callback',
                fn(): string => 'value',
                transformer: fn($v): mixed => $v,
            );
            $withoutTransformer = new CallbackSource(
                'callback',
                fn(): string => 'value',
            );

            expect($withTransformer->getMetadata()['has_transformer'])->toBeTrue();
            expect($withoutTransformer->getMetadata()['has_transformer'])->toBeFalse();
        });
    });

    describe('complex scenarios', function (): void {
        test('database lookup simulation', function (): void {
            $database = [
                'user:1' => ['id' => 1, 'name' => 'Alice'],
                'user:2' => ['id' => 2, 'name' => 'Bob'],
            ];

            $source = new CallbackSource(
                'database',
                fn(string $key): ?array => $database[$key] ?? null,
                fn(string $key): bool => str_starts_with($key, 'user:'),
            );

            expect($source->supports('user:1', []))->toBeTrue();
            expect($source->supports('config:key', []))->toBeFalse();
            expect($source->get('user:1', []))->toBe(['id' => 1, 'name' => 'Alice']);
            expect($source->get('user:99', []))->toBeNull();
        });

        test('environment-specific configuration', function (): void {
            $source = new CallbackSource(
                'env-config',
                function (string $key, array $context): ?string {
                    $env = $context['env'] ?? 'development';

                    return match ($key) {
                        'db.host' => $env === 'production' ? 'prod.db.com' : 'localhost',
                        'api.url' => $env === 'production' ? 'https://api.prod.com' : 'http://localhost:3000',
                        default => null,
                    };
                },
            );

            expect($source->get('db.host', ['env' => 'production']))->toBe('prod.db.com');
            expect($source->get('db.host', ['env' => 'development']))->toBe('localhost');
            expect($source->get('api.url', ['env' => 'production']))->toBe('https://api.prod.com');
        });

        test('cached external API simulation', function (): void {
            $apiCallCount = 0;

            $source = new CallbackSource(
                'api',
                function (string $key) use (&$apiCallCount): string {
                    ++$apiCallCount;

                    return 'api-value-' . $key;
                },
            );

            // Multiple calls should each invoke the callback
            $source->get('key', []);
            $source->get('key', []);
            $source->get('key', []);

            expect($apiCallCount)->toBe(3);
        });

        test('value transformation with context awareness', function (): void {
            $source = new CallbackSource(
                'localized',
                fn(string $key): string => 'message.' . $key,
                transformer: fn($value) => strtoupper($value),
            );

            $value = $source->get('hello', []);

            expect($value)->toBe('MESSAGE.HELLO');
        });
    });

    describe('edge cases', function (): void {
        test('handles empty string key', function (): void {
            $source = new CallbackSource(
                'callback',
                fn(string $key): string => 'value-' . $key,
            );

            $value = $source->get('', []);

            expect($value)->toBe('value-');
        });

        test('handles empty context', function (): void {
            $receivedContext = null;

            $source = new CallbackSource(
                'callback',
                function (string $key, array $context) use (&$receivedContext): string {
                    $receivedContext = $context;

                    return 'value';
                },
            );

            $source->get('key', []);

            expect($receivedContext)->toBe([]);
        });

        test('resolver can return various types', function (): void {
            $stringSource = new CallbackSource('string', fn(): string => 'string');
            expect($stringSource->get('key', []))->toBe('string');

            $intSource = new CallbackSource('int', fn(): int => 42);
            expect($intSource->get('key', []))->toBe(42);

            $arraySource = new CallbackSource('array', fn(): array => ['key' => 'value']);
            expect($arraySource->get('key', []))->toBe(['key' => 'value']);

            $boolSource = new CallbackSource('bool', fn(): true => true);
            expect($boolSource->get('key', []))->toBe(true);

            $objectSource = new CallbackSource('object', fn() => (object) ['key' => 'value']);
            expect($objectSource->get('key', []))->toEqual((object) ['key' => 'value']);
        });
    });
});
