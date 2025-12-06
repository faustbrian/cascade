<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Repository\DatabaseRepository;

describe('DatabaseRepository', function (): void {
    beforeEach(function (): void {
        // Create in-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create default resolvers table
        $this->pdo->exec('
            CREATE TABLE resolvers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                definition TEXT NOT NULL
            )
        ');
    });

    describe('get()', function (): void {
        describe('happy path', function (): void {
            test('retrieves resolver definition by name', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('user.email', '{\"type\": \"string\", \"default\": \"user@example.com\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('user.email');

                // Assert
                expect($definition)->toBe([
                    'type' => 'string',
                    'default' => 'user@example.com',
                ]);
            });

            test('retrieves multiple different resolvers', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('config.app', '{\"name\": \"My App\"}'),
                    ('config.db', '{\"host\": \"localhost\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $app = $repository->get('config.app');
                $db = $repository->get('config.db');

                // Assert
                expect($app)->toBe(['name' => 'My App']);
                expect($db)->toBe(['host' => 'localhost']);
            });

            test('handles complex JSON structures', function (): void {
                // Arrange
                $complexJson = json_encode([
                    'sources' => [
                        ['name' => 'source1', 'priority' => 1],
                        ['name' => 'source2', 'priority' => 2],
                    ],
                    'fallback' => ['enabled' => true],
                    'metadata' => ['version' => '1.0.0'],
                ]);

                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('complex', '{$complexJson}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('complex');

                // Assert
                expect($definition)->toHaveKey('sources');
                expect($definition['sources'])->toHaveCount(2);
                expect($definition['fallback']['enabled'])->toBe(true);
            });

            test('handles unicode characters in JSON', function (): void {
                // Arrange
                $unicodeJson = json_encode([
                    'message' => 'Hello 世界 🌍',
                    'emoji' => '🚀',
                ]);

                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('unicode', '{$unicodeJson}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('unicode');

                // Assert
                expect($definition['message'])->toBe('Hello 世界 🌍');
                expect($definition['emoji'])->toBe('🚀');
            });
        });

        describe('sad path', function (): void {
            test('throws exception when resolver not found', function (): void {
                // Arrange
                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect(fn (): array => $repository->get('non.existent'))
                    ->toThrow(ResolverNotFoundException::class, "Resolver 'non.existent' not found");
            });

            test('throws exception for invalid JSON', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('invalid', 'not valid json{]')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect(fn (): array => $repository->get('invalid'))
                    ->toThrow(RuntimeException::class, "Invalid JSON definition for resolver 'invalid'");
            });

            test('throws exception when JSON is not an object/array', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('string', '\"just a string\"')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect(fn (): array => $repository->get('string'))
                    ->toThrow(RuntimeException::class, "Invalid definition for resolver 'string': expected object/array");
            });

            test('throws exception when JSON is null', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('null_value', 'null')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect(fn (): array => $repository->get('null_value'))
                    ->toThrow(RuntimeException::class, "Invalid definition for resolver 'null_value'");
            });

            test('throws exception when JSON is a number', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('number', '42')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect(fn (): array => $repository->get('number'))
                    ->toThrow(RuntimeException::class, "Invalid definition for resolver 'number'");
            });
        });

        describe('edge cases', function (): void {
            test('handles empty JSON object', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('empty', '{}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('empty');

                // Assert
                expect($definition)->toBe([]);
            });

            test('handles empty JSON array', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('empty_array', '[]')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('empty_array');

                // Assert
                expect($definition)->toBe([]);
            });

            test('handles resolver name with special characters', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('config.app:name-v2', '{\"value\": \"test\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('config.app:name-v2');

                // Assert
                expect($definition)->toBe(['value' => 'test']);
            });

            test('handles very long JSON strings', function (): void {
                // Arrange
                $longArray = array_fill(0, 1_000, ['key' => 'value']);
                $longJson = json_encode($longArray);

                $stmt = $this->pdo->prepare("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('long', ?)
                ");
                $stmt->execute([$longJson]);

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $definition = $repository->get('long');

                // Assert
                expect($definition)->toHaveCount(1_000);
            });
        });
    });

    describe('has()', function (): void {
        describe('happy path', function (): void {
            test('returns true when resolver exists', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('existing', '{\"key\": \"value\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $exists = $repository->has('existing');

                // Assert
                expect($exists)->toBe(true);
            });

            test('returns false when resolver does not exist', function (): void {
                // Arrange
                $repository = new DatabaseRepository($this->pdo);

                // Act
                $exists = $repository->has('non.existent');

                // Assert
                expect($exists)->toBe(false);
            });

            test('checks multiple resolvers correctly', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('resolver1', '{}'),
                    ('resolver2', '{}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect($repository->has('resolver1'))->toBe(true);
                expect($repository->has('resolver2'))->toBe(true);
                expect($repository->has('resolver3'))->toBe(false);
            });
        });

        describe('edge cases', function (): void {
            test('returns true even if JSON is invalid', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('invalid_json', 'not json')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $exists = $repository->has('invalid_json');

                // Assert
                expect($exists)->toBe(true);
            });

            test('handles empty string name', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('', '{}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $exists = $repository->has('');

                // Assert
                expect($exists)->toBe(true);
            });
        });
    });

    describe('all()', function (): void {
        describe('happy path', function (): void {
            test('returns all resolver definitions', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('config1', '{\"key1\": \"value1\"}'),
                    ('config2', '{\"key2\": \"value2\"}'),
                    ('config3', '{\"key3\": \"value3\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $all = $repository->all();

                // Assert
                expect($all)->toHaveCount(3);
                expect($all)->toHaveKey('config1');
                expect($all)->toHaveKey('config2');
                expect($all)->toHaveKey('config3');
                expect($all['config1'])->toBe(['key1' => 'value1']);
            });

            test('returns empty array when no resolvers exist', function (): void {
                // Arrange
                $repository = new DatabaseRepository($this->pdo);

                // Act
                $all = $repository->all();

                // Assert
                expect($all)->toBe([]);
            });

            test('returns resolvers indexed by name', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('alpha', '{\"value\": 1}'),
                    ('beta', '{\"value\": 2}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $all = $repository->all();

                // Assert
                expect(array_keys($all))->toBe(['alpha', 'beta']);
            });
        });

        describe('caching behavior', function (): void {
            test('caches results on first call', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('cached', '{\"value\": \"original\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $first = $repository->all();

                // Modify database after first call
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('new', '{\"value\": \"new\"}')
                ");

                $second = $repository->all();

                // Assert
                expect($first)->toHaveCount(1);
                expect($second)->toHaveCount(1);
                expect($second)->toBe($first);
            });

            test('returns same reference on subsequent calls', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('test', '{}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $first = $repository->all();
                $second = $repository->all();
                $third = $repository->all();

                // Assert
                expect($first === $second)->toBe(true);
                expect($second === $third)->toBe(true);
            });
        });

        describe('edge cases', function (): void {
            test('handles resolvers with duplicate names', function (): void {
                // Arrange
                // SQLite UNIQUE constraint prevents this, so we expect an exception
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('duplicate', '{\"value\": 1}')
                ");

                // Act & Assert
                expect(fn () => $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('duplicate', '{\"value\": 2}')
                "))->toThrow(PDOException::class);
            });

            test('skips resolvers with invalid JSON gracefully', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('valid', '{\"key\": \"value\"}'),
                    ('invalid', 'not json')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act & Assert
                expect(fn (): array => $repository->all())
                    ->toThrow(RuntimeException::class, "Invalid JSON definition for resolver 'invalid'");
            });

            test('handles large number of resolvers', function (): void {
                // Arrange
                $stmt = $this->pdo->prepare('
                    INSERT INTO resolvers (name, definition) VALUES (?, ?)
                ');

                for ($i = 0; $i < 100; ++$i) {
                    $stmt->execute(['resolver'.$i, json_encode(['id' => $i])]);
                }

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $all = $repository->all();

                // Assert
                expect($all)->toHaveCount(100);
                expect($all['resolver0'])->toBe(['id' => 0]);
                expect($all['resolver99'])->toBe(['id' => 99]);
            });
        });
    });

    describe('getMany()', function (): void {
        describe('happy path', function (): void {
            test('retrieves multiple resolvers by name', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('config1', '{\"value\": 1}'),
                    ('config2', '{\"value\": 2}'),
                    ('config3', '{\"value\": 3}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany(['config1', 'config3']);

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
                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany([]);

                // Assert
                expect($many)->toBe([]);
            });

            test('returns only existing resolvers', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('existing1', '{}'),
                    ('existing2', '{}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany(['existing1', 'non.existent', 'existing2']);

                // Assert
                expect($many)->toHaveCount(2);
                expect($many)->toHaveKey('existing1');
                expect($many)->toHaveKey('existing2');
                expect($many)->not->toHaveKey('non.existent');
            });

            test('retrieves single resolver', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('single', '{\"key\": \"value\"}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany(['single']);

                // Assert
                expect($many)->toHaveCount(1);
                expect($many['single'])->toBe(['key' => 'value']);
            });
        });

        describe('caching interaction', function (): void {
            test('uses cache when available', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('cached1', '{\"value\": 1}'),
                    ('cached2', '{\"value\": 2}'),
                    ('cached3', '{\"value\": 3}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Pre-populate cache
                $repository->all();

                // Modify database
                $this->pdo->exec("DELETE FROM resolvers WHERE name = 'cached2'");

                // Act
                $many = $repository->getMany(['cached1', 'cached2', 'cached3']);

                // Assert - should still have cached2 from cache
                expect($many)->toHaveCount(3);
                expect($many)->toHaveKey('cached2');
            });

            test('queries database when cache not available', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('uncached1', '{\"value\": 1}'),
                    ('uncached2', '{\"value\": 2}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany(['uncached1', 'uncached2']);

                // Assert
                expect($many)->toHaveCount(2);
            });
        });

        describe('edge cases', function (): void {
            test('handles duplicate names in request', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition)
                    VALUES ('duplicate', '{\"value\": 1}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany(['duplicate', 'duplicate', 'duplicate']);

                // Assert
                expect($many)->toHaveCount(1);
                expect($many['duplicate'])->toBe(['value' => 1]);
            });

            test('handles large number of requested names', function (): void {
                // Arrange
                $stmt = $this->pdo->prepare('
                    INSERT INTO resolvers (name, definition) VALUES (?, ?)
                ');

                $names = [];

                for ($i = 0; $i < 50; ++$i) {
                    $name = 'resolver'.$i;
                    $stmt->execute([$name, json_encode(['id' => $i])]);
                    $names[] = $name;
                }

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany($names);

                // Assert
                expect($many)->toHaveCount(50);
            });

            test('handles special characters in names', function (): void {
                // Arrange
                $this->pdo->exec("
                    INSERT INTO resolvers (name, definition) VALUES
                    ('name:with:colons', '{}'),
                    ('name.with.dots', '{}'),
                    ('name-with-dashes', '{}')
                ");

                $repository = new DatabaseRepository($this->pdo);

                // Act
                $many = $repository->getMany(['name:with:colons', 'name.with.dots', 'name-with-dashes']);

                // Assert
                expect($many)->toHaveCount(3);
            });
        });
    });

    describe('custom table configuration', function (): void {
        test('uses custom table name', function (): void {
            // Arrange
            $this->pdo->exec('
                CREATE TABLE custom_resolvers (
                    name TEXT NOT NULL,
                    definition TEXT NOT NULL
                )
            ');

            $this->pdo->exec("
                INSERT INTO custom_resolvers (name, definition)
                VALUES ('test', '{\"key\": \"value\"}')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                table: 'custom_resolvers',
            );

            // Act
            $definition = $repository->get('test');

            // Assert
            expect($definition)->toBe(['key' => 'value']);
        });

        test('uses custom column names', function (): void {
            // Arrange
            $this->pdo->exec('
                CREATE TABLE config (
                    config_key TEXT NOT NULL,
                    config_value TEXT NOT NULL
                )
            ');

            $this->pdo->exec("
                INSERT INTO config (config_key, config_value)
                VALUES ('app.name', '{\"value\": \"My App\"}')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                table: 'config',
                nameColumn: 'config_key',
                definitionColumn: 'config_value',
            );

            // Act
            $definition = $repository->get('app.name');

            // Assert
            expect($definition)->toBe(['value' => 'My App']);
        });

        test('all methods work with custom configuration', function (): void {
            // Arrange
            $this->pdo->exec('
                CREATE TABLE settings (
                    setting_name TEXT NOT NULL,
                    setting_json TEXT NOT NULL
                )
            ');

            $this->pdo->exec("
                INSERT INTO settings (setting_name, setting_json) VALUES
                ('setting1', '{\"enabled\": true}'),
                ('setting2', '{\"enabled\": false}')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                table: 'settings',
                nameColumn: 'setting_name',
                definitionColumn: 'setting_json',
            );

            // Act & Assert
            expect($repository->has('setting1'))->toBe(true);
            expect($repository->get('setting1'))->toBe(['enabled' => true]);
            expect($repository->all())->toHaveCount(2);
            expect($repository->getMany(['setting1', 'setting2']))->toHaveCount(2);
        });
    });

    describe('additional WHERE conditions', function (): void {
        beforeEach(function (): void {
            // Create table with additional columns
            $this->pdo->exec('DROP TABLE IF EXISTS resolvers');
            $this->pdo->exec('
                CREATE TABLE resolvers (
                    name TEXT NOT NULL,
                    definition TEXT NOT NULL,
                    tenant_id INTEGER NOT NULL,
                    status TEXT NOT NULL
                )
            ');
        });

        test('filters by single condition', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition, tenant_id, status) VALUES
                ('config1', '{}', 1, 'active'),
                ('config2', '{}', 2, 'active'),
                ('config3', '{}', 1, 'inactive')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                conditions: ['tenant_id' => 1],
            );

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toHaveCount(2);
            expect($all)->toHaveKey('config1');
            expect($all)->toHaveKey('config3');
            expect($all)->not->toHaveKey('config2');
        });

        test('filters by multiple conditions', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition, tenant_id, status) VALUES
                ('config1', '{}', 1, 'active'),
                ('config2', '{}', 2, 'active'),
                ('config3', '{}', 1, 'inactive')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                conditions: ['tenant_id' => 1, 'status' => 'active'],
            );

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toHaveCount(1);
            expect($all)->toHaveKey('config1');
        });

        test('get() respects conditions', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition, tenant_id, status) VALUES
                ('shared', '{\"tenant\": 1}', 1, 'active'),
                ('shared', '{\"tenant\": 2}', 2, 'active')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                conditions: ['tenant_id' => 1],
            );

            // Act
            $definition = $repository->get('shared');

            // Assert
            expect($definition)->toBe(['tenant' => 1]);
        });

        test('has() respects conditions', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition, tenant_id, status) VALUES
                ('test', '{}', 1, 'active'),
                ('test', '{}', 2, 'active')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                conditions: ['tenant_id' => 2],
            );

            // Act
            $exists = $repository->has('test');

            // Assert
            expect($exists)->toBe(true);
        });

        test('getMany() respects conditions', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition, tenant_id, status) VALUES
                ('config1', '{\"id\": 1}', 1, 'active'),
                ('config2', '{\"id\": 2}', 1, 'active'),
                ('config3', '{\"id\": 3}', 2, 'active')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                conditions: ['tenant_id' => 1],
            );

            // Act
            $many = $repository->getMany(['config1', 'config2', 'config3']);

            // Assert
            expect($many)->toHaveCount(2);
            expect($many)->toHaveKey('config1');
            expect($many)->toHaveKey('config2');
            expect($many)->not->toHaveKey('config3');
        });

        test('throws exception when resolver not found due to conditions', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition, tenant_id, status)
                VALUES ('test', '{}', 1, 'active')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                conditions: ['tenant_id' => 2],
            );

            // Act & Assert
            expect(fn (): array => $repository->get('test'))
                ->toThrow(ResolverNotFoundException::class);
        });

        test('combines custom conditions with custom table/columns', function (): void {
            // Arrange
            $this->pdo->exec('
                CREATE TABLE tenant_settings (
                    key TEXT NOT NULL,
                    value TEXT NOT NULL,
                    tenant INTEGER NOT NULL,
                    env TEXT NOT NULL
                )
            ');

            $this->pdo->exec("
                INSERT INTO tenant_settings (key, value, tenant, env) VALUES
                ('setting1', '{\"data\": 1}', 5, 'production'),
                ('setting2', '{\"data\": 2}', 5, 'staging'),
                ('setting3', '{\"data\": 3}', 6, 'production')
            ");

            $repository = new DatabaseRepository(
                pdo: $this->pdo,
                table: 'tenant_settings',
                nameColumn: 'key',
                definitionColumn: 'value',
                conditions: ['tenant' => 5, 'env' => 'production'],
            );

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toHaveCount(1);
            expect($all)->toHaveKey('setting1');
            expect($all['setting1'])->toBe(['data' => 1]);
        });
    });

    describe('SQL injection protection', function (): void {
        test('handles malicious resolver names safely', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition)
                VALUES ('normal', '{}')
            ");

            $repository = new DatabaseRepository($this->pdo);
            $maliciousName = "test' OR '1'='1";

            // Act
            $exists = $repository->has($maliciousName);
            $all = $repository->all();

            // Assert
            expect($exists)->toBe(false);
            expect($all)->toHaveCount(1);
        });

        test('handles malicious resolver names in get()', function (): void {
            // Arrange
            $repository = new DatabaseRepository($this->pdo);
            $maliciousName = "test'; DROP TABLE resolvers; --";

            // Act & Assert
            expect(fn (): array => $repository->get($maliciousName))
                ->toThrow(ResolverNotFoundException::class);

            // Verify table still exists
            $tableExists = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='resolvers'")->fetch();
            expect($tableExists)->not->toBe(false);
        });

        test('handles malicious names in getMany()', function (): void {
            // Arrange
            $this->pdo->exec("
                INSERT INTO resolvers (name, definition)
                VALUES ('valid', '{}')
            ");

            $repository = new DatabaseRepository($this->pdo);

            // Act
            $many = $repository->getMany([
                'valid',
                "'; DELETE FROM resolvers; --",
                "' OR '1'='1",
            ]);

            // Assert
            expect($many)->toHaveCount(1);
            expect($many)->toHaveKey('valid');

            // Verify table data still intact
            $count = $this->pdo->query('SELECT COUNT(*) FROM resolvers')->fetchColumn();
            expect($count)->toBe(1);
        });
    });

    describe('database error handling', function (): void {
        test('handles database connection errors gracefully', function (): void {
            // Arrange
            $pdo = new PDO('sqlite::memory:');
            $repository = new DatabaseRepository($pdo);

            // Act & Assert - table doesn't exist
            expect(fn (): array => $repository->get('test'))
                ->toThrow(PDOException::class);
        });
    });
});
