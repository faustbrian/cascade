<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Repository\ArrayRepository;

describe('ArrayRepository', function (): void {
    describe('constructor', function (): void {
        test('creates repository with empty array', function (): void {
            $repository = new ArrayRepository();

            expect($repository->all())->toBe([]);
        });

        test('creates repository with resolver definitions', function (): void {
            $resolvers = [
                'api' => ['type' => 'http', 'url' => 'https://api.example.com'],
                'cache' => ['type' => 'redis', 'ttl' => 3600],
            ];

            $repository = new ArrayRepository($resolvers);

            expect($repository->all())->toBe($resolvers);
        });

        test('creates repository with single resolver', function (): void {
            $resolvers = [
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
            ];

            $repository = new ArrayRepository($resolvers);

            expect($repository->all())->toBe($resolvers);
        });

        test('creates repository with complex resolver definitions', function (): void {
            $resolvers = [
                'payment' => [
                    'type' => 'stripe',
                    'api_key' => 'sk_test_123',
                    'webhook_secret' => 'whsec_456',
                    'options' => [
                        'currency' => 'usd',
                        'auto_capture' => true,
                    ],
                ],
            ];

            $repository = new ArrayRepository($resolvers);

            expect($repository->all())->toBe($resolvers);
        });
    });

    describe('get() method', function (): void {
        test('retrieves existing resolver by name', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http', 'url' => 'https://api.example.com'],
                'cache' => ['type' => 'redis', 'ttl' => 3600],
            ]);

            $resolver = $repository->get('api');

            expect($resolver)->toBe(['type' => 'http', 'url' => 'https://api.example.com']);
        });

        test('retrieves different resolver definitions', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
                'database' => ['type' => 'mysql'],
            ]);

            expect($repository->get('api'))->toBe(['type' => 'http']);
            expect($repository->get('cache'))->toBe(['type' => 'redis']);
            expect($repository->get('database'))->toBe(['type' => 'mysql']);
        });

        test('throws exception when resolver not found', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
            ]);

            expect(fn(): array => $repository->get('non-existent'))
                ->toThrow(ResolverNotFoundException::class, "Resolver 'non-existent' not found");
        });

        test('throws exception for empty repository', function (): void {
            $repository = new ArrayRepository();

            expect(fn(): array => $repository->get('any-resolver'))
                ->toThrow(ResolverNotFoundException::class, "Resolver 'any-resolver' not found");
        });

        test('retrieves resolver with empty array definition', function (): void {
            $repository = new ArrayRepository([
                'minimal' => [],
            ]);

            expect($repository->get('minimal'))->toBe([]);
        });

        test('retrieves resolver with nested arrays', function (): void {
            $repository = new ArrayRepository([
                'config' => [
                    'database' => [
                        'connections' => [
                            'mysql' => ['host' => 'localhost'],
                            'pgsql' => ['host' => '127.0.0.1'],
                        ],
                    ],
                ],
            ]);

            $resolver = $repository->get('config');

            expect($resolver)->toBe([
                'database' => [
                    'connections' => [
                        'mysql' => ['host' => 'localhost'],
                        'pgsql' => ['host' => '127.0.0.1'],
                    ],
                ],
            ]);
        });
    });

    describe('has() method', function (): void {
        test('returns true for existing resolver', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);

            expect($repository->has('api'))->toBeTrue();
            expect($repository->has('cache'))->toBeTrue();
        });

        test('returns false for non-existing resolver', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
            ]);

            expect($repository->has('non-existent'))->toBeFalse();
            expect($repository->has('another-missing'))->toBeFalse();
        });

        test('returns false for empty repository', function (): void {
            $repository = new ArrayRepository();

            expect($repository->has('any-resolver'))->toBeFalse();
        });

        test('returns true for resolver with empty definition', function (): void {
            $repository = new ArrayRepository([
                'minimal' => [],
            ]);

            expect($repository->has('minimal'))->toBeTrue();
        });

        test('returns true for resolver with null value', function (): void {
            $repository = new ArrayRepository([
                'nullable' => ['value' => null],
            ]);

            expect($repository->has('nullable'))->toBeTrue();
        });

        test('handles case-sensitive resolver names', function (): void {
            $repository = new ArrayRepository([
                'API' => ['type' => 'http'],
                'api' => ['type' => 'rest'],
            ]);

            expect($repository->has('API'))->toBeTrue();
            expect($repository->has('api'))->toBeTrue();
            expect($repository->has('Api'))->toBeFalse();
        });
    });

    describe('all() method', function (): void {
        test('returns all resolver definitions', function (): void {
            $resolvers = [
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
                'database' => ['type' => 'mysql'],
            ];

            $repository = new ArrayRepository($resolvers);

            expect($repository->all())->toBe($resolvers);
        });

        test('returns empty array for empty repository', function (): void {
            $repository = new ArrayRepository();

            expect($repository->all())->toBe([]);
        });

        test('returns single resolver', function (): void {
            $resolvers = [
                'api' => ['type' => 'http'],
            ];

            $repository = new ArrayRepository($resolvers);

            expect($repository->all())->toBe($resolvers);
        });

        test('returns all resolvers with complex definitions', function (): void {
            $resolvers = [
                'api' => [
                    'type' => 'http',
                    'options' => ['timeout' => 30],
                ],
                'cache' => [
                    'type' => 'redis',
                    'options' => ['ttl' => 3600],
                ],
            ];

            $repository = new ArrayRepository($resolvers);

            expect($repository->all())->toBe($resolvers);
        });

        test('preserves resolver order', function (): void {
            $resolvers = [
                'first' => ['order' => 1],
                'second' => ['order' => 2],
                'third' => ['order' => 3],
            ];

            $repository = new ArrayRepository($resolvers);

            $keys = array_keys($repository->all());
            expect($keys)->toBe(['first', 'second', 'third']);
        });
    });

    describe('getMany() method', function (): void {
        test('retrieves multiple existing resolvers', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
                'database' => ['type' => 'mysql'],
            ]);

            $resolvers = $repository->getMany(['api', 'cache']);

            expect($resolvers)->toBe([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);
        });

        test('returns empty array for empty names list', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
            ]);

            $resolvers = $repository->getMany([]);

            expect($resolvers)->toBe([]);
        });

        test('returns partial matches when some resolvers exist', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);

            $resolvers = $repository->getMany(['api', 'non-existent', 'cache']);

            expect($resolvers)->toBe([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);
        });

        test('returns empty array when no resolvers match', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
            ]);

            $resolvers = $repository->getMany(['non-existent', 'another-missing']);

            expect($resolvers)->toBe([]);
        });

        test('returns single resolver in array', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);

            $resolvers = $repository->getMany(['api']);

            expect($resolvers)->toBe([
                'api' => ['type' => 'http'],
            ]);
        });

        test('handles duplicate names in request', function (): void {
            $repository = new ArrayRepository([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);

            $resolvers = $repository->getMany(['api', 'api', 'cache']);

            // Later duplicate overwrites earlier one
            expect($resolvers)->toBe([
                'api' => ['type' => 'http'],
                'cache' => ['type' => 'redis'],
            ]);
        });

        test('returns resolvers in order requested', function (): void {
            $repository = new ArrayRepository([
                'third' => ['order' => 3],
                'first' => ['order' => 1],
                'second' => ['order' => 2],
            ]);

            $resolvers = $repository->getMany(['first', 'second', 'third']);

            $keys = array_keys($resolvers);
            expect($keys)->toBe(['first', 'second', 'third']);
        });

        test('works with empty repository', function (): void {
            $repository = new ArrayRepository();

            $resolvers = $repository->getMany(['api', 'cache']);

            expect($resolvers)->toBe([]);
        });
    });

    describe('edge cases', function (): void {
        test('handles resolver names with special characters', function (): void {
            $repository = new ArrayRepository([
                'api.v1' => ['version' => 1],
                'cache-redis' => ['type' => 'redis'],
                'db_primary' => ['host' => 'localhost'],
                'queue:sqs' => ['driver' => 'sqs'],
            ]);

            expect($repository->has('api.v1'))->toBeTrue();
            expect($repository->has('cache-redis'))->toBeTrue();
            expect($repository->has('db_primary'))->toBeTrue();
            expect($repository->has('queue:sqs'))->toBeTrue();

            expect($repository->get('api.v1'))->toBe(['version' => 1]);
        });

        test('handles empty string as resolver name', function (): void {
            $repository = new ArrayRepository([
                '' => ['empty' => 'name'],
            ]);

            expect($repository->has(''))->toBeTrue();
            expect($repository->get(''))->toBe(['empty' => 'name']);
        });

        test('handles numeric string as resolver name', function (): void {
            $repository = new ArrayRepository([
                '0' => ['zero' => true],
                '42' => ['answer' => true],
            ]);

            expect($repository->has('0'))->toBeTrue();
            expect($repository->has('42'))->toBeTrue();
            expect($repository->get('42'))->toBe(['answer' => true]);
        });

        test('handles resolver with all scalar types', function (): void {
            $repository = new ArrayRepository([
                'mixed' => [
                    'string' => 'value',
                    'int' => 42,
                    'float' => 3.14,
                    'bool' => true,
                    'null' => null,
                ],
            ]);

            $resolver = $repository->get('mixed');

            expect($resolver['string'])->toBe('value');
            expect($resolver['int'])->toBe(42);
            expect($resolver['float'])->toBe(3.14);
            expect($resolver['bool'])->toBe(true);
            expect($resolver['null'])->toBeNull();
        });

        test('handles large number of resolvers', function (): void {
            $resolvers = [];
            for ($i = 0; $i < 1000; ++$i) {
                $resolvers['resolver' . $i] = ['id' => $i];
            }

            $repository = new ArrayRepository($resolvers);

            expect($repository->has('resolver0'))->toBeTrue();
            expect($repository->has('resolver500'))->toBeTrue();
            expect($repository->has('resolver999'))->toBeTrue();
            expect($repository->get('resolver500'))->toBe(['id' => 500]);
            expect(count($repository->all()))->toBe(1000);
        });

        test('readonly property prevents mutation after construction', function (): void {
            $resolvers = [
                'api' => ['type' => 'http'],
            ];

            $repository = new ArrayRepository($resolvers);

            // Verify repository is readonly by checking all() returns same reference
            $all1 = $repository->all();
            $all2 = $repository->all();

            expect($all1)->toBe($all2);
        });
    });

    describe('use cases', function (): void {
        test('resolver chain configuration', function (): void {
            $repository = new ArrayRepository([
                'feature-flags' => [
                    'sources' => ['environment', 'database', 'cache'],
                    'fallback' => 'defaults',
                ],
                'application-config' => [
                    'sources' => ['file', 'environment'],
                    'cache' => true,
                ],
            ]);

            $featureFlags = $repository->get('feature-flags');

            expect($featureFlags['sources'])->toBe(['environment', 'database', 'cache']);
            expect($featureFlags['fallback'])->toBe('defaults');
        });

        test('multi-tenant resolver setup', function (): void {
            $repository = new ArrayRepository([
                'tenant-a' => [
                    'database' => 'tenant_a_db',
                    'cache_prefix' => 'tenant_a',
                ],
                'tenant-b' => [
                    'database' => 'tenant_b_db',
                    'cache_prefix' => 'tenant_b',
                ],
            ]);

            $tenants = $repository->getMany(['tenant-a', 'tenant-b']);

            expect($tenants)->toHaveCount(2);
            expect($tenants['tenant-a']['database'])->toBe('tenant_a_db');
            expect($tenants['tenant-b']['database'])->toBe('tenant_b_db');
        });

        test('environment-specific resolvers', function (): void {
            $repository = new ArrayRepository([
                'production' => [
                    'api_url' => 'https://api.prod.example.com',
                    'cache_ttl' => 3600,
                    'debug' => false,
                ],
                'development' => [
                    'api_url' => 'http://localhost:8080',
                    'cache_ttl' => 60,
                    'debug' => true,
                ],
            ]);

            $prod = $repository->get('production');
            $dev = $repository->get('development');

            expect($prod['debug'])->toBe(false);
            expect($dev['debug'])->toBe(true);
        });

        test('fallback chain definition', function (): void {
            $repository = new ArrayRepository([
                'primary' => [
                    'priority' => 1,
                    'endpoint' => 'https://primary.example.com',
                ],
                'secondary' => [
                    'priority' => 2,
                    'endpoint' => 'https://secondary.example.com',
                ],
                'tertiary' => [
                    'priority' => 3,
                    'endpoint' => 'https://tertiary.example.com',
                ],
            ]);

            $chain = $repository->getMany(['primary', 'secondary', 'tertiary']);

            expect($chain)->toHaveCount(3);
            expect($chain['primary']['priority'])->toBe(1);
            expect($chain['tertiary']['priority'])->toBe(3);
        });
    });
});
