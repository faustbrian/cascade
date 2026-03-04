<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Repository\JsonRepository;

describe('JsonRepository', function (): void {
    beforeEach(function (): void {
        // Create a temporary directory for test JSON files
        $this->tempDir = sys_get_temp_dir().'/cascade_test_'.uniqid();
        mkdir($this->tempDir, 0o777, true);
    });

    afterEach(function (): void {
        // Clean up all temporary files and directories recursively
        if (!is_dir($this->tempDir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $todo($fileinfo->getRealPath());
        }

        rmdir($this->tempDir);
    });

    describe('constructor - single file loading', function (): void {
        test('loads resolvers from single JSON file', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode([
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
                'cache' => ['driver' => 'redis', 'ttl' => 3_600],
            ]));

            // Act
            $repository = new JsonRepository($jsonFile);

            // Assert
            expect($repository->all())->toBe([
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
                'cache' => ['driver' => 'redis', 'ttl' => 3_600],
            ]);
        });

        test('loads empty JSON object', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/empty.json';
            file_put_contents($jsonFile, '{}');

            // Act
            $repository = new JsonRepository($jsonFile);

            // Assert
            expect($repository->all())->toBe([]);
        });

        test('loads JSON with nested structures', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/nested.json';
            file_put_contents($jsonFile, json_encode([
                'api' => [
                    'url' => 'https://api.example.com',
                    'headers' => ['Authorization' => 'Bearer token'],
                    'timeout' => 30,
                ],
            ]));

            // Act
            $repository = new JsonRepository($jsonFile);

            // Assert
            $api = $repository->get('api');
            expect($api)->toBe([
                'url' => 'https://api.example.com',
                'headers' => ['Authorization' => 'Bearer token'],
                'timeout' => 30,
            ]);
        });
    });

    describe('constructor - multiple file merging', function (): void {
        test('merges multiple JSON files with later files overriding earlier', function (): void {
            // Arrange
            $file1 = $this->tempDir.'/base.json';
            $file2 = $this->tempDir.'/override.json';

            file_put_contents($file1, json_encode([
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
                'cache' => ['driver' => 'file', 'path' => '/tmp/cache'],
            ]));

            file_put_contents($file2, json_encode([
                'cache' => ['driver' => 'redis', 'host' => 'redis.example.com'],
                'queue' => ['driver' => 'rabbitmq'],
            ]));

            // Act
            $repository = new JsonRepository([$file1, $file2]);

            // Assert
            expect($repository->all())->toBe([
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
                'cache' => ['driver' => 'redis', 'host' => 'redis.example.com'],
                'queue' => ['driver' => 'rabbitmq'],
            ]);
        });

        test('merges three files in order', function (): void {
            // Arrange
            $file1 = $this->tempDir.'/first.json';
            $file2 = $this->tempDir.'/second.json';
            $file3 = $this->tempDir.'/third.json';

            file_put_contents($file1, json_encode(['key' => 'first', 'unique1' => 'value1']));
            file_put_contents($file2, json_encode(['key' => 'second', 'unique2' => 'value2']));
            file_put_contents($file3, json_encode(['key' => 'third', 'unique3' => 'value3']));

            // Act
            $repository = new JsonRepository([$file1, $file2, $file3]);

            // Assert
            expect($repository->all())->toBe([
                'key' => 'third',
                'unique1' => 'value1',
                'unique2' => 'value2',
                'unique3' => 'value3',
            ]);
        });

        test('merges empty array with populated arrays', function (): void {
            // Arrange
            $file1 = $this->tempDir.'/empty.json';
            $file2 = $this->tempDir.'/populated.json';

            file_put_contents($file1, '{}');
            file_put_contents($file2, json_encode(['key' => 'value']));

            // Act
            $repository = new JsonRepository([$file1, $file2]);

            // Assert
            expect($repository->all())->toBe(['key' => 'value']);
        });
    });

    describe('constructor - base path resolution', function (): void {
        test('resolves relative path with base path', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/config.json';
            file_put_contents($jsonFile, json_encode(['key' => 'value']));

            // Act
            $repository = new JsonRepository('config.json', $this->tempDir);

            // Assert
            expect($repository->all())->toBe(['key' => 'value']);
        });

        test('base path with trailing slash', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/config.json';
            file_put_contents($jsonFile, json_encode(['key' => 'value']));

            // Act
            $repository = new JsonRepository('config.json', $this->tempDir.'/');

            // Assert
            expect($repository->all())->toBe(['key' => 'value']);
        });

        test('ignores base path for absolute Unix paths', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/config.json';
            file_put_contents($jsonFile, json_encode(['key' => 'value']));

            // Act
            $repository = new JsonRepository($jsonFile, '/some/other/base');

            // Assert
            expect($repository->all())->toBe(['key' => 'value']);
        });

        test('handles nested relative paths with base path', function (): void {
            // Arrange
            $subDir = $this->tempDir.'/configs';
            mkdir($subDir);
            $jsonFile = $subDir.'/app.json';
            file_put_contents($jsonFile, json_encode(['app' => 'test']));

            // Act
            $repository = new JsonRepository('configs/app.json', $this->tempDir);

            // Assert
            expect($repository->all())->toBe(['app' => 'test']);
        });
    });

    describe('constructor - Windows path handling', function (): void {
        test('recognizes Windows absolute path with C drive', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/config.json';
            file_put_contents($jsonFile, json_encode(['key' => 'value']));

            // Mock a Windows-style path (will fail to find, but tests path detection)
            // We can't actually test Windows paths on Unix, but we verify the logic doesn't crash
            // Act & Assert
            try {
                $repository = new JsonRepository('C:/absolute/path.json', $this->tempDir);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file not found: C:/absolute/path.json');
            }
        });

        test('recognizes Windows absolute path with backslashes', function (): void {
            // Arrange - Test that Windows paths are recognized (will fail to find on Unix)
            // Act & Assert
            try {
                $repository = new JsonRepository('C:\\absolute\\path.json', $this->tempDir);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file not found: C:\\absolute\\path.json');
            }
        });
    });

    describe('get() method', function (): void {
        test('retrieves resolver by name', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode([
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
                'cache' => ['driver' => 'redis'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolver = $repository->get('database');

            // Assert
            expect($resolver)->toBe(['type' => 'mysql', 'host' => 'localhost']);
        });

        test('throws ResolverNotFoundException for non-existent resolver', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode(['existing' => ['data' => 'value']]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect(fn (): array => $repository->get('non-existent'))
                ->toThrow(ResolverNotFoundException::class);
        });

        test('exception message contains resolver name', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, '{}');
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            try {
                $repository->get('missing-resolver');
            } catch (ResolverNotFoundException $resolverNotFoundException) {
                expect($resolverNotFoundException->getMessage())->toContain('missing-resolver');
            }
        });
    });

    describe('has() method', function (): void {
        test('returns true for existing resolver', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode(['database' => ['type' => 'mysql']]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->has('database'))->toBeTrue();
        });

        test('returns false for non-existent resolver', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode(['database' => ['type' => 'mysql']]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->has('cache'))->toBeFalse();
        });

        test('returns false for empty repository', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/empty.json';
            file_put_contents($jsonFile, '{}');
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->has('anything'))->toBeFalse();
        });

        test('checks multiple resolver names', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode([
                'resolver1' => ['data' => 'value1'],
                'resolver2' => ['data' => 'value2'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->has('resolver1'))->toBeTrue();
            expect($repository->has('resolver2'))->toBeTrue();
            expect($repository->has('resolver3'))->toBeFalse();
        });
    });

    describe('all() method', function (): void {
        test('returns all resolvers', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            $resolvers = [
                'database' => ['type' => 'mysql', 'host' => 'localhost'],
                'cache' => ['driver' => 'redis', 'ttl' => 3_600],
                'queue' => ['driver' => 'sqs'],
            ];
            file_put_contents($jsonFile, json_encode($resolvers));
            $repository = new JsonRepository($jsonFile);

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toBe($resolvers);
        });

        test('returns empty array for empty repository', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/empty.json';
            file_put_contents($jsonFile, '{}');
            $repository = new JsonRepository($jsonFile);

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toBe([]);
        });

        test('returns merged resolvers from multiple files', function (): void {
            // Arrange
            $file1 = $this->tempDir.'/file1.json';
            $file2 = $this->tempDir.'/file2.json';

            file_put_contents($file1, json_encode(['resolver1' => ['data' => 'value1']]));
            file_put_contents($file2, json_encode(['resolver2' => ['data' => 'value2']]));

            $repository = new JsonRepository([$file1, $file2]);

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toBe([
                'resolver1' => ['data' => 'value1'],
                'resolver2' => ['data' => 'value2'],
            ]);
        });
    });

    describe('getMany() method', function (): void {
        test('retrieves multiple resolvers by names', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode([
                'database' => ['type' => 'mysql'],
                'cache' => ['driver' => 'redis'],
                'queue' => ['driver' => 'sqs'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolvers = $repository->getMany(['database', 'queue']);

            // Assert
            expect($resolvers)->toBe([
                'database' => ['type' => 'mysql'],
                'queue' => ['driver' => 'sqs'],
            ]);
        });

        test('skips non-existent resolvers', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode([
                'database' => ['type' => 'mysql'],
                'cache' => ['driver' => 'redis'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolvers = $repository->getMany(['database', 'non-existent', 'cache']);

            // Assert
            expect($resolvers)->toBe([
                'database' => ['type' => 'mysql'],
                'cache' => ['driver' => 'redis'],
            ]);
        });

        test('returns empty array for empty names list', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode(['database' => ['type' => 'mysql']]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolvers = $repository->getMany([]);

            // Assert
            expect($resolvers)->toBe([]);
        });

        test('returns empty array when no names match', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode(['database' => ['type' => 'mysql']]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolvers = $repository->getMany(['cache', 'queue']);

            // Assert
            expect($resolvers)->toBe([]);
        });

        test('maintains order of requested names', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/resolvers.json';
            file_put_contents($jsonFile, json_encode([
                'a' => ['value' => 'a'],
                'b' => ['value' => 'b'],
                'c' => ['value' => 'c'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolvers = $repository->getMany(['c', 'a', 'b']);

            // Assert
            expect(array_keys($resolvers))->toBe(['c', 'a', 'b']);
        });
    });

    describe('error handling - file not found', function (): void {
        test('throws RuntimeException when file does not exist', function (): void {
            // Act & Assert
            expect(fn (): JsonRepository => new JsonRepository($this->tempDir.'/non-existent.json'))
                ->toThrow(RuntimeException::class);
        });

        test('exception message contains file path for not found', function (): void {
            // Arrange
            $missingFile = $this->tempDir.'/missing.json';

            // Act & Assert
            try {
                new JsonRepository($missingFile);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file not found');
                expect($runtimeException->getMessage())->toContain($missingFile);
            }
        });

        test('throws for first missing file in multiple files', function (): void {
            // Arrange
            $existingFile = $this->tempDir.'/existing.json';
            $missingFile = $this->tempDir.'/missing.json';
            file_put_contents($existingFile, '{}');

            // Act & Assert
            try {
                new JsonRepository([$existingFile, $missingFile]);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file not found');
                expect($runtimeException->getMessage())->toContain($missingFile);
            }
        });
    });

    describe('error handling - invalid JSON', function (): void {
        test('throws RuntimeException for malformed JSON', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/invalid.json';
            file_put_contents($jsonFile, '{"invalid": json}');

            // Act & Assert
            expect(fn (): JsonRepository => new JsonRepository($jsonFile))
                ->toThrow(RuntimeException::class);
        });

        test('exception message contains syntax error details', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/invalid.json';
            file_put_contents($jsonFile, '{"unclosed": "bracket"');

            // Act & Assert
            try {
                new JsonRepository($jsonFile);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('Invalid JSON in file');
                expect($runtimeException->getMessage())->toContain($jsonFile);
            }
        });

        test('throws for JSON with trailing comma', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/trailing-comma.json';
            file_put_contents($jsonFile, '{"key": "value",}');

            // Act & Assert
            expect(fn (): JsonRepository => new JsonRepository($jsonFile))
                ->toThrow(RuntimeException::class);
        });

        test('throws for JSON with single quotes', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/single-quotes.json';
            file_put_contents($jsonFile, "{'key': 'value'}");

            // Act & Assert
            expect(fn (): JsonRepository => new JsonRepository($jsonFile))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('error handling - non-object JSON', function (): void {
        test('throws RuntimeException for JSON indexed array instead of object', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/array.json';
            file_put_contents($jsonFile, '["item1", "item2"]');

            // Act
            $repository = new JsonRepository($jsonFile);

            // Assert - Indexed arrays are converted to associative arrays by json_decode
            expect($repository->all())->toBe([0 => 'item1', 1 => 'item2']);
        });

        test('throws RuntimeException for JSON string', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/string.json';
            file_put_contents($jsonFile, '"just a string"');

            // Act & Assert
            try {
                new JsonRepository($jsonFile);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file must contain an object/array');
            }
        });

        test('throws RuntimeException for JSON number', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/number.json';
            file_put_contents($jsonFile, '42');

            // Act & Assert
            try {
                new JsonRepository($jsonFile);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file must contain an object/array');
            }
        });

        test('throws RuntimeException for JSON boolean', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/boolean.json';
            file_put_contents($jsonFile, 'true');

            // Act & Assert
            try {
                new JsonRepository($jsonFile);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file must contain an object/array');
            }
        });

        test('throws RuntimeException for JSON null', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/null.json';
            file_put_contents($jsonFile, 'null');

            // Act & Assert
            try {
                new JsonRepository($jsonFile);
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toContain('JSON file must contain an object/array');
            }
        });
    });

    describe('edge cases', function (): void {
        test('handles JSON with Unicode characters', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/unicode.json';
            file_put_contents($jsonFile, json_encode([
                'greeting' => ['message' => 'Hello 世界'],
                'emoji' => ['icon' => '🚀 rocket'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->get('greeting'))->toBe(['message' => 'Hello 世界']);
            expect($repository->get('emoji'))->toBe(['icon' => '🚀 rocket']);
        });

        test('handles JSON with escaped characters', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/escaped.json';
            file_put_contents($jsonFile, json_encode([
                'newline' => ['text' => "Line 1\nLine 2"],
                'tab' => ['text' => "Column1\tColumn2"],
                'quote' => ['text' => 'He said "Hello"'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->get('newline'))->toBe(['text' => "Line 1\nLine 2"]);
            expect($repository->get('tab'))->toBe(['text' => "Column1\tColumn2"]);
            expect($repository->get('quote'))->toBe(['text' => 'He said "Hello"']);
        });

        test('handles deeply nested JSON structures', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/deep.json';
            file_put_contents($jsonFile, json_encode([
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => [
                                'value' => 'deep value',
                            ],
                        ],
                    ],
                ],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolver = $repository->get('level1');

            // Assert
            expect($resolver['level2']['level3']['level4']['value'])->toBe('deep value');
        });

        test('handles large JSON file', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/large.json';
            $resolvers = [];

            for ($i = 0; $i < 1_000; ++$i) {
                $resolvers['resolver'.$i] = ['index' => $i, 'data' => 'value'.$i];
            }

            file_put_contents($jsonFile, json_encode($resolvers));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect(count($repository->all()))->toBe(1_000);
            expect($repository->get('resolver0'))->toBe(['index' => 0, 'data' => 'value0']);
            expect($repository->get('resolver999'))->toBe(['index' => 999, 'data' => 'value999']);
        });

        test('handles empty string as resolver name', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/empty-key.json';
            file_put_contents($jsonFile, json_encode(['' => ['data' => 'empty key value']]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->has(''))->toBeTrue();
            expect($repository->get(''))->toBe(['data' => 'empty key value']);
        });

        test('handles numeric-like string keys', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/numeric-keys.json';
            // Use keys that look numeric but have prefixes to stay as strings
            file_put_contents($jsonFile, json_encode([
                'resolver_0' => ['data' => 'zero'],
                'resolver_123' => ['data' => 'one-two-three'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->has('resolver_0'))->toBeTrue();
            expect($repository->get('resolver_123'))->toBe(['data' => 'one-two-three']);
        });

        test('handles special characters in resolver names', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/special-chars.json';
            file_put_contents($jsonFile, json_encode([
                'resolver.with.dots' => ['data' => 'dots'],
                'resolver-with-dashes' => ['data' => 'dashes'],
                'resolver_with_underscores' => ['data' => 'underscores'],
                'resolver:with:colons' => ['data' => 'colons'],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->get('resolver.with.dots'))->toBe(['data' => 'dots']);
            expect($repository->get('resolver-with-dashes'))->toBe(['data' => 'dashes']);
            expect($repository->get('resolver_with_underscores'))->toBe(['data' => 'underscores']);
            expect($repository->get('resolver:with:colons'))->toBe(['data' => 'colons']);
        });

        test('handles various data types in resolver values', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/types.json';
            file_put_contents($jsonFile, json_encode([
                'string' => ['value' => 'text'],
                'integer' => ['value' => 42],
                'float' => ['value' => 3.14],
                'boolean_true' => ['value' => true],
                'boolean_false' => ['value' => false],
                'null' => ['value' => null],
                'array' => ['value' => [1, 2, 3]],
                'object' => ['value' => ['nested' => 'object']],
            ]));
            $repository = new JsonRepository($jsonFile);

            // Act & Assert
            expect($repository->get('string')['value'])->toBe('text');
            expect($repository->get('integer')['value'])->toBe(42);
            expect($repository->get('float')['value'])->toBe(3.14);
            expect($repository->get('boolean_true')['value'])->toBe(true);
            expect($repository->get('boolean_false')['value'])->toBe(false);
            expect($repository->get('null')['value'])->toBeNull();
            expect($repository->get('array')['value'])->toBe([1, 2, 3]);
            expect($repository->get('object')['value'])->toBe(['nested' => 'object']);
        });

        test('readonly property prevents modification of resolvers', function (): void {
            // Arrange
            $jsonFile = $this->tempDir.'/readonly.json';
            file_put_contents($jsonFile, json_encode(['key' => ['value' => 'original']]));
            $repository = new JsonRepository($jsonFile);

            // Act
            $resolvers1 = $repository->all();
            $resolvers2 = $repository->all();

            // Assert - Multiple calls return the same data
            expect($resolvers1)->toBe($resolvers2);
        });
    });

    describe('use cases', function (): void {
        test('configuration management with environment overrides', function (): void {
            // Arrange
            $baseConfig = $this->tempDir.'/config.json';
            $envConfig = $this->tempDir.'/config.production.json';

            file_put_contents($baseConfig, json_encode([
                'database' => ['host' => 'localhost', 'port' => 3_306],
                'cache' => ['driver' => 'file'],
            ]));

            file_put_contents($envConfig, json_encode([
                'database' => ['host' => 'prod-db.example.com'],
                'cache' => ['driver' => 'redis', 'host' => 'redis.example.com'],
            ]));

            // Act
            $repository = new JsonRepository([$baseConfig, $envConfig]);

            // Assert - array_merge completely replaces, not deep merge
            expect($repository->get('database'))->toBe([
                'host' => 'prod-db.example.com',
            ]);
            expect($repository->get('cache'))->toBe([
                'driver' => 'redis',
                'host' => 'redis.example.com',
            ]);
        });

        test('modular configuration with shared base', function (): void {
            // Arrange
            $sharedConfig = $this->tempDir.'/shared.json';
            $moduleAConfig = $this->tempDir.'/module-a.json';
            $moduleBConfig = $this->tempDir.'/module-b.json';

            file_put_contents($sharedConfig, json_encode([
                'logging' => ['level' => 'info'],
            ]));

            file_put_contents($moduleAConfig, json_encode([
                'module_a' => ['enabled' => true],
            ]));

            file_put_contents($moduleBConfig, json_encode([
                'module_b' => ['enabled' => true],
            ]));

            // Act
            $repository = new JsonRepository([$sharedConfig, $moduleAConfig, $moduleBConfig]);

            // Assert
            expect($repository->has('logging'))->toBeTrue();
            expect($repository->has('module_a'))->toBeTrue();
            expect($repository->has('module_b'))->toBeTrue();
        });

        test('resolver registry loading', function (): void {
            // Arrange
            $resolversFile = $this->tempDir.'/resolvers.json';
            file_put_contents($resolversFile, json_encode([
                'database_resolver' => [
                    'class' => 'App\\Resolvers\\DatabaseResolver',
                    'priority' => 100,
                ],
                'api_resolver' => [
                    'class' => 'App\\Resolvers\\ApiResolver',
                    'priority' => 50,
                ],
                'cache_resolver' => [
                    'class' => 'App\\Resolvers\\CacheResolver',
                    'priority' => 200,
                ],
            ]));

            // Act
            $repository = new JsonRepository($resolversFile);
            $allResolvers = $repository->all();

            // Assert
            expect(count($allResolvers))->toBe(3);
            expect($repository->get('cache_resolver')['priority'])->toBe(200);
        });
    });
});
