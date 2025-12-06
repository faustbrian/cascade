<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Database\ModelRegistry;
use Cline\Cascade\Database\Models\Resolver;
use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Repository\EloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentRepository', function (): void {
    beforeEach(function (): void {
        // Create a ModelRegistry instance
        $this->registry = resolve(ModelRegistry::class);
        $this->repository = new EloquentRepository($this->registry);
    });

    describe('get()', function (): void {
        describe('happy path', function (): void {
            test('retrieves active resolver definition by name', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'user.email',
                    'definition' => ['type' => 'string', 'default' => 'user@example.com'],
                    'is_active' => true,
                ]);

                // Act
                $definition = $this->repository->get('user.email');

                // Assert
                expect($definition)->toBe([
                    'type' => 'string',
                    'default' => 'user@example.com',
                ]);
            });

            test('retrieves multiple different resolvers', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'config.app',
                    'definition' => ['name' => 'My App'],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'config.db',
                    'definition' => ['host' => 'localhost'],
                    'is_active' => true,
                ]);

                // Act
                $app = $this->repository->get('config.app');
                $db = $this->repository->get('config.db');

                // Assert
                expect($app)->toBe(['name' => 'My App']);
                expect($db)->toBe(['host' => 'localhost']);
            });

            test('handles complex nested definition structures', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'complex',
                    'definition' => [
                        'sources' => [
                            ['name' => 'source1', 'priority' => 1],
                            ['name' => 'source2', 'priority' => 2],
                        ],
                        'fallback' => ['enabled' => true],
                        'metadata' => ['version' => '1.0.0'],
                    ],
                    'is_active' => true,
                ]);

                // Act
                $definition = $this->repository->get('complex');

                // Assert
                expect($definition)->toHaveKey('sources');
                expect($definition['sources'])->toHaveCount(2);
                expect($definition['fallback']['enabled'])->toBe(true);
            });
        });

        describe('sad path', function (): void {
            test('throws exception when resolver not found', function (): void {
                // Arrange
                // No resolver created

                // Act & Assert
                expect(fn (): array => $this->repository->get('non.existent'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'non.existent' not found");
            });

            test('throws exception when resolver exists but is inactive', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'inactive.resolver',
                    'definition' => ['key' => 'value'],
                    'is_active' => false,
                ]);

                // Act & Assert
                expect(fn (): array => $this->repository->get('inactive.resolver'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'inactive.resolver' not found");
            });
        });

        describe('edge cases', function (): void {
            test('handles empty definition array', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'empty',
                    'definition' => [],
                    'is_active' => true,
                ]);

                // Act
                $definition = $this->repository->get('empty');

                // Assert
                expect($definition)->toBe([]);
            });

            test('handles resolver name with special characters', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'config.app:name-v2',
                    'definition' => ['value' => 'test'],
                    'is_active' => true,
                ]);

                // Act
                $definition = $this->repository->get('config.app:name-v2');

                // Assert
                expect($definition)->toBe(['value' => 'test']);
            });

            test('handles very large definition arrays', function (): void {
                // Arrange
                $largeDefinition = [
                    'sources' => array_fill(0, 100, ['name' => 'source', 'config' => ['key' => 'value']]),
                    'metadata' => ['version' => '1.0.0'],
                ];

                Resolver::factory()->create([
                    'name' => 'large.def',
                    'definition' => $largeDefinition,
                    'is_active' => true,
                ]);

                // Act
                $definition = $this->repository->get('large.def');

                // Assert
                expect($definition['sources'])->toHaveCount(100);
                expect($definition['metadata']['version'])->toBe('1.0.0');
            });
        });
    });

    describe('has()', function (): void {
        describe('happy path', function (): void {
            test('returns true when active resolver exists', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'existing',
                    'definition' => ['key' => 'value'],
                    'is_active' => true,
                ]);

                // Act
                $exists = $this->repository->has('existing');

                // Assert
                expect($exists)->toBe(true);
            });

            test('returns false when resolver does not exist', function (): void {
                // Arrange
                // No resolver created

                // Act
                $exists = $this->repository->has('non.existent');

                // Assert
                expect($exists)->toBe(false);
            });

            test('checks multiple resolvers correctly', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'resolver1', 'is_active' => true]);
                Resolver::factory()->create(['name' => 'resolver2', 'is_active' => true]);

                // Act & Assert
                expect($this->repository->has('resolver1'))->toBe(true);
                expect($this->repository->has('resolver2'))->toBe(true);
                expect($this->repository->has('resolver3'))->toBe(false);
            });
        });

        describe('sad path', function (): void {
            test('returns false when resolver exists but is inactive', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'inactive',
                    'definition' => ['key' => 'value'],
                    'is_active' => false,
                ]);

                // Act
                $exists = $this->repository->has('inactive');

                // Assert
                expect($exists)->toBe(false);
            });
        });

        describe('edge cases', function (): void {
            test('handles empty string name', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => '',
                    'is_active' => true,
                ]);

                // Act
                $exists = $this->repository->has('');

                // Assert
                expect($exists)->toBe(true);
            });
        });
    });

    describe('all()', function (): void {
        describe('happy path', function (): void {
            test('returns all active resolver definitions', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'config1',
                    'definition' => ['key1' => 'value1'],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'config2',
                    'definition' => ['key2' => 'value2'],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'config3',
                    'definition' => ['key3' => 'value3'],
                    'is_active' => true,
                ]);

                // Act
                $all = $this->repository->all();

                // Assert
                expect($all)->toHaveCount(3);
                expect($all)->toHaveKey('config1');
                expect($all)->toHaveKey('config2');
                expect($all)->toHaveKey('config3');
                expect($all['config1'])->toBe(['key1' => 'value1']);
            });

            test('returns empty array when no resolvers exist', function (): void {
                // Arrange
                // No resolvers created

                // Act
                $all = $this->repository->all();

                // Assert
                expect($all)->toBe([]);
            });

            test('returns resolvers indexed by name', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'alpha',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'beta',
                    'definition' => ['value' => 2],
                    'is_active' => true,
                ]);

                // Act
                $all = $this->repository->all();

                // Assert
                expect(array_keys($all))->toContain('alpha');
                expect(array_keys($all))->toContain('beta');
            });
        });

        describe('active/inactive filtering', function (): void {
            test('excludes inactive resolvers', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'active1',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'inactive1',
                    'definition' => ['value' => 2],
                    'is_active' => false,
                ]);

                Resolver::factory()->create([
                    'name' => 'active2',
                    'definition' => ['value' => 3],
                    'is_active' => true,
                ]);

                // Act
                $all = $this->repository->all();

                // Assert
                expect($all)->toHaveCount(2);
                expect($all)->toHaveKey('active1');
                expect($all)->toHaveKey('active2');
                expect($all)->not->toHaveKey('inactive1');
            });

            test('returns empty array when all resolvers are inactive', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'inactive1', 'is_active' => false]);
                Resolver::factory()->create(['name' => 'inactive2', 'is_active' => false]);

                // Act
                $all = $this->repository->all();

                // Assert
                expect($all)->toBe([]);
            });
        });

        describe('edge cases', function (): void {
            test('handles large number of resolvers', function (): void {
                // Arrange
                for ($i = 0; $i < 100; ++$i) {
                    Resolver::factory()->create([
                        'name' => 'resolver'.$i,
                        'definition' => ['id' => $i],
                        'is_active' => true,
                    ]);
                }

                // Act
                $all = $this->repository->all();

                // Assert
                expect($all)->toHaveCount(100);
                expect($all['resolver0'])->toBe(['id' => 0]);
                expect($all['resolver99'])->toBe(['id' => 99]);
            });

            test('handles mixed definition types correctly', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'simple',
                    'definition' => ['key' => 'value'],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'complex',
                    'definition' => [
                        'nested' => ['deep' => ['very' => 'deep']],
                        'array' => [1, 2, 3],
                    ],
                    'is_active' => true,
                ]);

                // Act
                $all = $this->repository->all();

                // Assert
                expect($all)->toHaveCount(2);
                expect($all['simple'])->toBe(['key' => 'value']);
                expect($all['complex']['nested']['deep']['very'])->toBe('deep');
            });
        });
    });

    describe('getMany()', function (): void {
        describe('happy path', function (): void {
            test('retrieves multiple active resolvers by name', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'config1',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'config2',
                    'definition' => ['value' => 2],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'config3',
                    'definition' => ['value' => 3],
                    'is_active' => true,
                ]);

                // Act
                $many = $this->repository->getMany(['config1', 'config3']);

                // Assert
                expect($many)->toHaveCount(2);
                expect($many)->toHaveKey('config1');
                expect($many)->toHaveKey('config3');
                expect($many)->not->toHaveKey('config2');
                expect($many['config1'])->toBe(['value' => 1]);
                expect($many['config3'])->toBe(['value' => 3]);
            });

            test('returns empty array for empty names array', function (): void {
                // Arrange
                // No setup needed

                // Act
                $many = $this->repository->getMany([]);

                // Assert
                expect($many)->toBe([]);
            });

            test('returns only existing resolvers', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'existing1',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'existing2',
                    'definition' => ['value' => 2],
                    'is_active' => true,
                ]);

                // Act
                $many = $this->repository->getMany(['existing1', 'non.existent', 'existing2']);

                // Assert
                expect($many)->toHaveCount(2);
                expect($many)->toHaveKey('existing1');
                expect($many)->toHaveKey('existing2');
                expect($many)->not->toHaveKey('non.existent');
            });

            test('retrieves single resolver', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'single',
                    'definition' => ['key' => 'value'],
                    'is_active' => true,
                ]);

                // Act
                $many = $this->repository->getMany(['single']);

                // Assert
                expect($many)->toHaveCount(1);
                expect($many['single'])->toBe(['key' => 'value']);
            });
        });

        describe('active/inactive filtering', function (): void {
            test('excludes inactive resolvers', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'active1',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'inactive1',
                    'definition' => ['value' => 2],
                    'is_active' => false,
                ]);

                Resolver::factory()->create([
                    'name' => 'active2',
                    'definition' => ['value' => 3],
                    'is_active' => true,
                ]);

                // Act
                $many = $this->repository->getMany(['active1', 'inactive1', 'active2']);

                // Assert
                expect($many)->toHaveCount(2);
                expect($many)->toHaveKey('active1');
                expect($many)->toHaveKey('active2');
                expect($many)->not->toHaveKey('inactive1');
            });
        });

        describe('edge cases', function (): void {
            test('handles duplicate names in request', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'duplicate',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                // Act
                $many = $this->repository->getMany(['duplicate', 'duplicate', 'duplicate']);

                // Assert
                expect($many)->toHaveCount(1);
                expect($many['duplicate'])->toBe(['value' => 1]);
            });

            test('handles large number of requested names', function (): void {
                // Arrange
                $names = [];

                for ($i = 0; $i < 50; ++$i) {
                    $name = 'resolver'.$i;
                    Resolver::factory()->create([
                        'name' => $name,
                        'definition' => ['id' => $i],
                        'is_active' => true,
                    ]);
                    $names[] = $name;
                }

                // Act
                $many = $this->repository->getMany($names);

                // Assert
                expect($many)->toHaveCount(50);
            });

            test('handles special characters in names', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'name:with:colons',
                    'definition' => ['value' => 1],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'name.with.dots',
                    'definition' => ['value' => 2],
                    'is_active' => true,
                ]);

                Resolver::factory()->create([
                    'name' => 'name-with-dashes',
                    'definition' => ['value' => 3],
                    'is_active' => true,
                ]);

                // Act
                $many = $this->repository->getMany(['name:with:colons', 'name.with.dots', 'name-with-dashes']);

                // Assert
                expect($many)->toHaveCount(3);
            });
        });
    });

    describe('save()', function (): void {
        describe('happy path', function (): void {
            test('creates new resolver', function (): void {
                // Arrange
                $name = 'new.resolver';
                $definition = ['type' => 'string', 'default' => 'test'];
                $description = 'Test resolver';

                // Act
                $model = $this->repository->save($name, $definition, $description);

                // Assert
                expect($model)->toBeInstanceOf(Resolver::class);
                expect($model->name)->toBe($name);
                expect($model->definition)->toBe($definition);
                expect($model->description)->toBe($description);
                expect($model->is_active)->toBe(true);
            });

            test('updates existing resolver', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'existing',
                    'definition' => ['old' => 'value'],
                    'description' => 'Old description',
                    'is_active' => false,
                ]);

                $newDefinition = ['new' => 'value'];
                $newDescription = 'New description';

                // Act
                $model = $this->repository->save('existing', $newDefinition, $newDescription);

                // Assert
                expect($model->name)->toBe('existing');
                expect($model->definition)->toBe($newDefinition);
                expect($model->description)->toBe($newDescription);
                expect($model->is_active)->toBe(true);

                // Verify only one record exists
                expect(Resolver::query()->where('name', 'existing')->count())->toBe(1);
            });

            test('saves without description', function (): void {
                // Arrange
                $name = 'no.description';
                $definition = ['key' => 'value'];

                // Act
                $model = $this->repository->save($name, $definition);

                // Assert
                expect($model->name)->toBe($name);
                expect($model->definition)->toBe($definition);
                expect($model->description)->toBeNull();
            });

            test('reactivates deactivated resolver on save', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'deactivated',
                    'definition' => ['old' => 'value'],
                    'is_active' => false,
                ]);

                // Act
                $model = $this->repository->save('deactivated', ['new' => 'value']);

                // Assert
                expect($model->is_active)->toBe(true);
            });
        });

        describe('edge cases', function (): void {
            test('handles empty definition array', function (): void {
                // Arrange
                $name = 'empty.def';
                $definition = [];

                // Act
                $model = $this->repository->save($name, $definition);

                // Assert
                expect($model->definition)->toBe([]);
            });

            test('handles complex nested definitions', function (): void {
                // Arrange
                $definition = [
                    'sources' => [
                        ['name' => 'source1', 'config' => ['key' => 'value']],
                        ['name' => 'source2', 'config' => ['key' => 'value']],
                    ],
                    'metadata' => ['version' => '2.0.0'],
                ];

                // Act
                $model = $this->repository->save('complex', $definition);

                // Assert
                expect($model->definition)->toBe($definition);
            });
        });
    });

    describe('delete()', function (): void {
        describe('happy path', function (): void {
            test('deletes existing resolver', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'to.delete',
                    'is_active' => true,
                ]);

                // Act
                $result = $this->repository->delete('to.delete');

                // Assert
                expect($result)->toBe(true);
                expect(Resolver::query()->where('name', 'to.delete')->exists())->toBe(false);
            });

            test('deletes inactive resolver', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'inactive.to.delete',
                    'is_active' => false,
                ]);

                // Act
                $result = $this->repository->delete('inactive.to.delete');

                // Assert
                expect($result)->toBe(true);
                expect(Resolver::query()->where('name', 'inactive.to.delete')->exists())->toBe(false);
            });

            test('returns false when resolver does not exist', function (): void {
                // Arrange
                // No resolver created

                // Act
                $result = $this->repository->delete('non.existent');

                // Assert
                expect($result)->toBe(false);
            });
        });

        describe('edge cases', function (): void {
            test('performs hard delete removing record completely', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'hard.delete']);

                // Act
                $this->repository->delete('hard.delete');

                // Assert
                expect(Resolver::query()->where('name', 'hard.delete')->exists())->toBe(false);
            });
        });
    });

    describe('deactivate()', function (): void {
        describe('happy path', function (): void {
            test('deactivates active resolver', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'to.deactivate',
                    'is_active' => true,
                ]);

                // Act
                $result = $this->repository->deactivate('to.deactivate');

                // Assert
                expect($result)->toBe(true);

                $resolver = Resolver::query()->where('name', 'to.deactivate')->first();
                expect($resolver->is_active)->toBe(false);
            });

            test('deactivating already inactive resolver returns true', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'already.inactive',
                    'is_active' => false,
                ]);

                // Act
                $result = $this->repository->deactivate('already.inactive');

                // Assert
                expect($result)->toBe(true);

                $resolver = Resolver::query()->where('name', 'already.inactive')->first();
                expect($resolver->is_active)->toBe(false);
            });

            test('returns false when resolver does not exist', function (): void {
                // Arrange
                // No resolver created

                // Act
                $result = $this->repository->deactivate('non.existent');

                // Assert
                expect($result)->toBe(false);
            });
        });

        describe('integration with other methods', function (): void {
            test('deactivated resolver not returned by get()', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'active',
                    'definition' => ['key' => 'value'],
                    'is_active' => true,
                ]);

                // Act
                $this->repository->deactivate('active');

                // Assert
                expect(fn (): array => $this->repository->get('active'))
                    ->toThrow(ResolverNotFoundException::class);
            });

            test('deactivated resolver not returned by all()', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'active1', 'is_active' => true]);
                Resolver::factory()->create(['name' => 'active2', 'is_active' => true]);

                // Act
                $this->repository->deactivate('active1');
                $all = $this->repository->all();

                // Assert
                expect($all)->toHaveCount(1);
                expect($all)->not->toHaveKey('active1');
                expect($all)->toHaveKey('active2');
            });

            test('deactivated resolver not returned by has()', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'active', 'is_active' => true]);

                // Act
                $this->repository->deactivate('active');

                // Assert
                expect($this->repository->has('active'))->toBe(false);
            });
        });
    });

    describe('reactivate()', function (): void {
        describe('happy path', function (): void {
            test('reactivates inactive resolver', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'to.reactivate',
                    'is_active' => false,
                ]);

                // Act
                $result = $this->repository->reactivate('to.reactivate');

                // Assert
                expect($result)->toBe(true);

                $resolver = Resolver::query()->where('name', 'to.reactivate')->first();
                expect($resolver->is_active)->toBe(true);
            });

            test('reactivating already active resolver returns true', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'already.active',
                    'is_active' => true,
                ]);

                // Act
                $result = $this->repository->reactivate('already.active');

                // Assert
                expect($result)->toBe(true);

                $resolver = Resolver::query()->where('name', 'already.active')->first();
                expect($resolver->is_active)->toBe(true);
            });

            test('returns false when resolver does not exist', function (): void {
                // Arrange
                // No resolver created

                // Act
                $result = $this->repository->reactivate('non.existent');

                // Assert
                expect($result)->toBe(false);
            });
        });

        describe('integration with other methods', function (): void {
            test('reactivated resolver returned by get()', function (): void {
                // Arrange
                Resolver::factory()->create([
                    'name' => 'inactive',
                    'definition' => ['key' => 'value'],
                    'is_active' => false,
                ]);

                // Act
                $this->repository->reactivate('inactive');
                $definition = $this->repository->get('inactive');

                // Assert
                expect($definition)->toBe(['key' => 'value']);
            });

            test('reactivated resolver returned by all()', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'inactive', 'is_active' => false]);

                // Act
                $this->repository->reactivate('inactive');
                $all = $this->repository->all();

                // Assert
                expect($all)->toHaveCount(1);
                expect($all)->toHaveKey('inactive');
            });

            test('reactivated resolver returned by has()', function (): void {
                // Arrange
                Resolver::factory()->create(['name' => 'inactive', 'is_active' => false]);

                // Act
                $this->repository->reactivate('inactive');

                // Assert
                expect($this->repository->has('inactive'))->toBe(true);
            });
        });
    });

    describe('deactivate/reactivate workflow', function (): void {
        test('supports complete deactivate and reactivate cycle', function (): void {
            // Arrange
            Resolver::factory()->create([
                'name' => 'toggle',
                'definition' => ['key' => 'value'],
                'is_active' => true,
            ]);

            // Act & Assert - Initial state
            expect($this->repository->has('toggle'))->toBe(true);

            // Act & Assert - Deactivate
            expect($this->repository->deactivate('toggle'))->toBe(true);
            expect($this->repository->has('toggle'))->toBe(false);

            // Act & Assert - Reactivate
            expect($this->repository->reactivate('toggle'))->toBe(true);
            expect($this->repository->has('toggle'))->toBe(true);

            // Verify definition preserved
            $definition = $this->repository->get('toggle');
            expect($definition)->toBe(['key' => 'value']);
        });

        test('multiple deactivate/reactivate cycles work correctly', function (): void {
            // Arrange
            Resolver::factory()->create([
                'name' => 'cycle',
                'is_active' => true,
            ]);

            // Act & Assert
            for ($i = 0; $i < 3; ++$i) {
                expect($this->repository->deactivate('cycle'))->toBe(true);
                expect($this->repository->has('cycle'))->toBe(false);

                expect($this->repository->reactivate('cycle'))->toBe(true);
                expect($this->repository->has('cycle'))->toBe(true);
            }
        });
    });

    describe('ModelRegistry integration', function (): void {
        test('uses model from registry', function (): void {
            // Arrange
            $modelClass = $this->registry->resolverModel();
            expect($modelClass)->toBe(Resolver::class);

            Resolver::factory()->create([
                'name' => 'test',
                'definition' => ['key' => 'value'],
                'is_active' => true,
            ]);

            // Act
            $definition = $this->repository->get('test');

            // Assert
            expect($definition)->toBe(['key' => 'value']);
        });
    });
});
