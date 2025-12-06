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
use Cline\Cascade\Repository\ChainedRepository;

describe('ChainedRepository', function (): void {
    describe('constructor', function (): void {
        test('throws InvalidArgumentException when initialized with empty array', function (): void {
            expect(fn(): ChainedRepository => new ChainedRepository([]))
                ->toThrow(\InvalidArgumentException::class, 'ChainedRepository requires at least one repository');
        });

        test('accepts single repository', function (): void {
            // Arrange
            $repo = new ArrayRepository(['resolver1' => ['key' => 'value']]);

            // Act
            $chained = new ChainedRepository([$repo]);

            // Assert
            expect($chained->has('resolver1'))->toBeTrue();
        });

        test('accepts multiple repositories', function (): void {
            // Arrange
            $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
            $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
            $repo3 = new ArrayRepository(['resolver3' => ['key' => 'value3']]);

            // Act
            $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

            // Assert
            expect($chained->has('resolver1'))->toBeTrue();
            expect($chained->has('resolver2'))->toBeTrue();
            expect($chained->has('resolver3'))->toBeTrue();
        });
    });

    describe('get()', function (): void {
        describe('happy path', function (): void {
            test('returns resolver from first repository when found', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->get('resolver1');

                // Assert
                expect($result)->toBe(['key' => 'value1']);
            });

            test('falls through to second repository when not in first', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->get('resolver2');

                // Assert
                expect($result)->toBe(['key' => 'value2']);
            });

            test('falls through entire chain to find resolver', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $repo3 = new ArrayRepository(['resolver3' => ['key' => 'value3']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->get('resolver3');

                // Assert
                expect($result)->toBe(['key' => 'value3']);
            });

            test('returns from first repository when resolver exists in multiple repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['shared' => ['priority' => 'first']]);
                $repo2 = new ArrayRepository(['shared' => ['priority' => 'second']]);
                $repo3 = new ArrayRepository(['shared' => ['priority' => 'third']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->get('shared');

                // Assert
                expect($result)->toBe(['priority' => 'first']);
            });

            test('returns from second repository when first does not have it', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['shared' => ['priority' => 'second']]);
                $repo3 = new ArrayRepository(['shared' => ['priority' => 'third']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->get('shared');

                // Assert
                expect($result)->toBe(['priority' => 'second']);
            });
        });

        describe('sad path', function (): void {
            test('throws ResolverNotFoundException when resolver not found in any repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act & Assert
                expect(fn(): array => $chained->get('nonexistent'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'nonexistent' not found");
            });

            test('throws ResolverNotFoundException with single repository', function (): void {
                // Arrange
                $repo = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $chained = new ChainedRepository([$repo]);

                // Act & Assert
                expect(fn(): array => $chained->get('missing'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'missing' not found");
            });

            test('throws ResolverNotFoundException when all repositories are empty', function (): void {
                // Arrange
                $repo1 = new ArrayRepository([]);
                $repo2 = new ArrayRepository([]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act & Assert
                expect(fn(): array => $chained->get('any'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'any' not found");
            });
        });
    });

    describe('has()', function (): void {
        describe('happy path', function (): void {
            test('returns true when resolver exists in first repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->has('resolver1');

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns true when resolver exists in second repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->has('resolver2');

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns true when resolver exists in any repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $repo3 = new ArrayRepository(['resolver3' => ['key' => 'value3']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act & Assert
                expect($chained->has('resolver1'))->toBeTrue();
                expect($chained->has('resolver2'))->toBeTrue();
                expect($chained->has('resolver3'))->toBeTrue();
            });

            test('returns true when resolver exists in multiple repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['shared' => ['priority' => 'first']]);
                $repo2 = new ArrayRepository(['shared' => ['priority' => 'second']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->has('shared');

                // Assert
                expect($result)->toBeTrue();
            });
        });

        describe('sad path', function (): void {
            test('returns false when resolver does not exist in any repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->has('nonexistent');

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when all repositories are empty', function (): void {
                // Arrange
                $repo1 = new ArrayRepository([]);
                $repo2 = new ArrayRepository([]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->has('any');

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false with single empty repository', function (): void {
                // Arrange
                $repo = new ArrayRepository([]);
                $chained = new ChainedRepository([$repo]);

                // Act
                $result = $chained->has('any');

                // Assert
                expect($result)->toBeFalse();
            });
        });
    });

    describe('all()', function (): void {
        describe('happy path', function (): void {
            test('returns all resolvers from single repository', function (): void {
                // Arrange
                $repo = new ArrayRepository([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
                $chained = new ChainedRepository([$repo]);

                // Act
                $result = $chained->all();

                // Assert
                expect($result)->toBe([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
            });

            test('merges resolvers from multiple repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->all();

                // Assert - order is repo2 then repo1 due to reverse iteration
                expect($result)->toBe([
                    'resolver2' => ['key' => 'value2'],
                    'resolver1' => ['key' => 'value1'],
                ]);
            });

            test('earlier repositories take precedence for duplicate names', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['shared' => ['priority' => 'first']]);
                $repo2 = new ArrayRepository(['shared' => ['priority' => 'second']]);
                $repo3 = new ArrayRepository(['shared' => ['priority' => 'third']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->all();

                // Assert
                expect($result)->toBe(['shared' => ['priority' => 'first']]);
            });

            test('merges unique and duplicate resolvers correctly', function (): void {
                // Arrange
                $repo1 = new ArrayRepository([
                    'unique1' => ['key' => 'value1'],
                    'shared' => ['priority' => 'first'],
                ]);
                $repo2 = new ArrayRepository([
                    'unique2' => ['key' => 'value2'],
                    'shared' => ['priority' => 'second'],
                ]);
                $repo3 = new ArrayRepository([
                    'unique3' => ['key' => 'value3'],
                    'shared' => ['priority' => 'third'],
                ]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->all();

                // Assert - reverse iteration means repo3, repo2, repo1 order, but earlier repos override
                expect($result)->toBe([
                    'unique3' => ['key' => 'value3'],
                    'shared' => ['priority' => 'first'],  // repo1 overrides repo2 and repo3
                    'unique2' => ['key' => 'value2'],
                    'unique1' => ['key' => 'value1'],
                ]);
            });

            test('returns empty array when all repositories are empty', function (): void {
                // Arrange
                $repo1 = new ArrayRepository([]);
                $repo2 = new ArrayRepository([]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->all();

                // Assert
                expect($result)->toBe([]);
            });

            test('includes resolvers only from non-empty repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository([]);
                $repo3 = new ArrayRepository(['resolver3' => ['key' => 'value3']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->all();

                // Assert - reverse iteration: repo3, repo2 (empty), repo1
                expect($result)->toBe([
                    'resolver3' => ['key' => 'value3'],
                    'resolver1' => ['key' => 'value1'],
                ]);
            });
        });

        describe('priority ordering', function (): void {
            test('first repository overrides second repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['config' => ['value' => 'override']]);
                $repo2 = new ArrayRepository(['config' => ['value' => 'default']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->all();

                // Assert
                expect($result['config'])->toBe(['value' => 'override']);
            });

            test('second repository overrides third repository', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['config' => ['value' => 'override']]);
                $repo3 = new ArrayRepository(['config' => ['value' => 'default']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->all();

                // Assert
                expect($result['config'])->toBe(['value' => 'override']);
            });

            test('demonstrates configuration override pattern', function (): void {
                // Arrange: local -> database -> defaults
                $local = new ArrayRepository([
                    'feature.enabled' => ['value' => true],
                    'feature.limit' => ['value' => 100],
                ]);
                $database = new ArrayRepository([
                    'feature.limit' => ['value' => 50],
                    'feature.timeout' => ['value' => 30],
                ]);
                $defaults = new ArrayRepository([
                    'feature.enabled' => ['value' => false],
                    'feature.limit' => ['value' => 10],
                    'feature.timeout' => ['value' => 60],
                ]);
                $chained = new ChainedRepository([$local, $database, $defaults]);

                // Act
                $result = $chained->all();

                // Assert
                expect($result)->toBe([
                    'feature.enabled' => ['value' => true],   // from local
                    'feature.limit' => ['value' => 100],      // from local
                    'feature.timeout' => ['value' => 30],     // from database
                ]);
            });
        });
    });

    describe('getMany()', function (): void {
        describe('happy path', function (): void {
            test('returns multiple resolvers from single repository', function (): void {
                // Arrange
                $repo = new ArrayRepository([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                    'resolver3' => ['key' => 'value3'],
                ]);
                $chained = new ChainedRepository([$repo]);

                // Act
                $result = $chained->getMany(['resolver1', 'resolver2']);

                // Assert
                expect($result)->toBe([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
            });

            test('retrieves resolvers from multiple repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->getMany(['resolver1', 'resolver2']);

                // Assert
                expect($result)->toBe([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
            });

            test('returns from first repository when resolver exists in multiple repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['shared' => ['priority' => 'first']]);
                $repo2 = new ArrayRepository(['shared' => ['priority' => 'second']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->getMany(['shared']);

                // Assert
                expect($result)->toBe(['shared' => ['priority' => 'first']]);
            });

            test('efficiently stops searching after finding all requested resolvers', function (): void {
                // Arrange
                $repo1 = new ArrayRepository([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
                $repo2 = new ArrayRepository(['resolver3' => ['key' => 'value3']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act - both found in first repo, shouldn't search second
                $result = $chained->getMany(['resolver1', 'resolver2']);

                // Assert
                expect($result)->toBe([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
            });

            test('continues searching for remaining resolvers', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $repo3 = new ArrayRepository(['resolver3' => ['key' => 'value3']]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->getMany(['resolver1', 'resolver2', 'resolver3']);

                // Assert
                expect($result)->toBe([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                    'resolver3' => ['key' => 'value3'],
                ]);
            });

            test('returns empty array when requesting empty array', function (): void {
                // Arrange
                $repo = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $chained = new ChainedRepository([$repo]);

                // Act
                $result = $chained->getMany([]);

                // Assert
                expect($result)->toBe([]);
            });

            test('returns only found resolvers when some are missing', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->getMany(['resolver1', 'nonexistent', 'resolver2']);

                // Assert
                expect($result)->toBe([
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
            });

            test('returns empty array when no resolvers found', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['resolver1' => ['key' => 'value1']]);
                $repo2 = new ArrayRepository(['resolver2' => ['key' => 'value2']]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->getMany(['nonexistent1', 'nonexistent2']);

                // Assert
                expect($result)->toBe([]);
            });
        });

        describe('priority and deduplication', function (): void {
            test('takes from first repository when duplicate exists', function (): void {
                // Arrange
                $repo1 = new ArrayRepository([
                    'shared' => ['priority' => 'first'],
                    'resolver1' => ['key' => 'value1'],
                ]);
                $repo2 = new ArrayRepository([
                    'shared' => ['priority' => 'second'],
                    'resolver2' => ['key' => 'value2'],
                ]);
                $chained = new ChainedRepository([$repo1, $repo2]);

                // Act
                $result = $chained->getMany(['shared', 'resolver1', 'resolver2']);

                // Assert
                expect($result)->toBe([
                    'shared' => ['priority' => 'first'],
                    'resolver1' => ['key' => 'value1'],
                    'resolver2' => ['key' => 'value2'],
                ]);
            });

            test('respects priority order across multiple repositories', function (): void {
                // Arrange
                $repo1 = new ArrayRepository(['a' => ['priority' => '1']]);
                $repo2 = new ArrayRepository([
                    'a' => ['priority' => '2'],
                    'b' => ['priority' => '2'],
                ]);
                $repo3 = new ArrayRepository([
                    'a' => ['priority' => '3'],
                    'b' => ['priority' => '3'],
                    'c' => ['priority' => '3'],
                ]);
                $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

                // Act
                $result = $chained->getMany(['a', 'b', 'c']);

                // Assert
                expect($result)->toBe([
                    'a' => ['priority' => '1'], // from repo1
                    'b' => ['priority' => '2'], // from repo2
                    'c' => ['priority' => '3'], // from repo3
                ]);
            });
        });
    });

    describe('edge cases', function (): void {
        test('handles single repository with single resolver', function (): void {
            // Arrange
            $repo = new ArrayRepository(['resolver' => ['key' => 'value']]);
            $chained = new ChainedRepository([$repo]);

            // Act & Assert
            expect($chained->has('resolver'))->toBeTrue();
            expect($chained->get('resolver'))->toBe(['key' => 'value']);
            expect($chained->all())->toBe(['resolver' => ['key' => 'value']]);
            expect($chained->getMany(['resolver']))->toBe(['resolver' => ['key' => 'value']]);
        });

        test('handles empty string as resolver name', function (): void {
            // Arrange
            $repo = new ArrayRepository(['' => ['key' => 'empty']]);
            $chained = new ChainedRepository([$repo]);

            // Act & Assert
            expect($chained->has(''))->toBeTrue();
            expect($chained->get(''))->toBe(['key' => 'empty']);
        });

        test('handles complex resolver definitions with nested arrays', function (): void {
            // Arrange
            $repo = new ArrayRepository([
                'complex' => [
                    'key' => 'value',
                    'nested' => [
                        'deep' => ['data' => 'here'],
                    ],
                    'array' => [1, 2, 3],
                ],
            ]);
            $chained = new ChainedRepository([$repo]);

            // Act
            $result = $chained->get('complex');

            // Assert
            expect($result)->toBe([
                'key' => 'value',
                'nested' => [
                    'deep' => ['data' => 'here'],
                ],
                'array' => [1, 2, 3],
            ]);
        });

        test('handles many repositories in chain', function (): void {
            // Arrange
            $repositories = [];
            for ($i = 1; $i <= 10; ++$i) {
                $repositories[] = new ArrayRepository(['resolver' . $i => ['index' => $i]]);
            }

            $chained = new ChainedRepository($repositories);

            // Act & Assert
            expect($chained->has('resolver5'))->toBeTrue();
            expect($chained->get('resolver10'))->toBe(['index' => 10]);
            expect($chained->all())->toHaveCount(10);
        });

        test('handles mix of empty and non-empty repositories', function (): void {
            // Arrange
            $repo1 = new ArrayRepository([]);
            $repo2 = new ArrayRepository(['resolver' => ['key' => 'value']]);
            $repo3 = new ArrayRepository([]);
            $chained = new ChainedRepository([$repo1, $repo2, $repo3]);

            // Act & Assert
            expect($chained->has('resolver'))->toBeTrue();
            expect($chained->get('resolver'))->toBe(['key' => 'value']);
        });

        test('handles getMany with duplicate names in request', function (): void {
            // Arrange
            $repo = new ArrayRepository(['resolver' => ['key' => 'value']]);
            $chained = new ChainedRepository([$repo]);

            // Act
            $result = $chained->getMany(['resolver', 'resolver', 'resolver']);

            // Assert - should not duplicate in result
            expect($result)->toBe(['resolver' => ['key' => 'value']]);
        });

        test('maintains resolver order from original repositories in all()', function (): void {
            // Arrange
            $repo1 = new ArrayRepository([
                'z' => ['order' => 'first'],
                'a' => ['order' => 'first'],
            ]);
            $repo2 = new ArrayRepository([
                'b' => ['order' => 'second'],
                'y' => ['order' => 'second'],
            ]);
            $chained = new ChainedRepository([$repo1, $repo2]);

            // Act
            $result = $chained->all();

            // Assert - reverse iteration: repo2 merged, then repo1 overrides
            expect(array_keys($result))->toBe(['b', 'y', 'z', 'a']);
        });
    });

    describe('integration scenarios', function (): void {
        test('simulates local-database-defaults configuration override pattern', function (): void {
            // Arrange
            $localConfig = new ArrayRepository([
                'api.endpoint' => ['value' => 'http://localhost:8000'],
            ]);
            $databaseConfig = new ArrayRepository([
                'api.timeout' => ['value' => 30],
                'api.retries' => ['value' => 3],
            ]);
            $defaultConfig = new ArrayRepository([
                'api.endpoint' => ['value' => 'https://api.example.com'],
                'api.timeout' => ['value' => 60],
                'api.retries' => ['value' => 5],
                'api.cache' => ['value' => true],
            ]);
            $chained = new ChainedRepository([$localConfig, $databaseConfig, $defaultConfig]);

            // Act
            $all = $chained->all();

            // Assert - local overrides database, database overrides defaults
            expect($all['api.endpoint'])->toBe(['value' => 'http://localhost:8000']); // local
            expect($all['api.timeout'])->toBe(['value' => 30]); // database
            expect($all['api.retries'])->toBe(['value' => 3]); // database
            expect($all['api.cache'])->toBe(['value' => true]); // defaults
        });

        test('simulates feature flag system with environment overrides', function (): void {
            // Arrange
            $envOverrides = new ArrayRepository([
                'feature.new_ui' => ['enabled' => true],
            ]);
            $userPreferences = new ArrayRepository([
                'feature.dark_mode' => ['enabled' => true],
            ]);
            $defaults = new ArrayRepository([
                'feature.new_ui' => ['enabled' => false],
                'feature.dark_mode' => ['enabled' => false],
                'feature.analytics' => ['enabled' => true],
            ]);
            $chained = new ChainedRepository([$envOverrides, $userPreferences, $defaults]);

            // Act & Assert
            expect($chained->get('feature.new_ui'))->toBe(['enabled' => true]); // env override
            expect($chained->get('feature.dark_mode'))->toBe(['enabled' => true]); // user pref
            expect($chained->get('feature.analytics'))->toBe(['enabled' => true]); // default
        });

        test('efficiently retrieves subset of resolvers', function (): void {
            // Arrange
            $repo1 = new ArrayRepository([
                'resolver1' => ['key' => 'value1'],
                'resolver2' => ['key' => 'value2'],
            ]);
            $repo2 = new ArrayRepository([
                'resolver3' => ['key' => 'value3'],
                'resolver4' => ['key' => 'value4'],
            ]);
            $chained = new ChainedRepository([$repo1, $repo2]);

            // Act
            $result = $chained->getMany(['resolver1', 'resolver3']);

            // Assert
            expect($result)->toBe([
                'resolver1' => ['key' => 'value1'],
                'resolver3' => ['key' => 'value3'],
            ]);
        });
    });
});
