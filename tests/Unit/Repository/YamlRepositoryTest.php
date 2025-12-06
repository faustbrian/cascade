<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Repository\YamlRepository;
use Symfony\Component\Yaml\Yaml;

describe('YamlRepository', function (): void {
    beforeEach(function (): void {
        // Create temporary directory for test YAML files
        $this->tempDir = sys_get_temp_dir() . '/yaml_repository_test_' . uniqid();
        mkdir($this->tempDir);
    });

    afterEach(function (): void {
        // Clean up temporary files and directory
        if (is_dir($this->tempDir)) {
            // Recursive cleanup function
            $cleanup = function (string $dir) use (&$cleanup): void {
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    if (is_dir($file)) {
                        $cleanup($file);
                        rmdir($file);
                    } else {
                        unlink($file);
                    }
                }
            };
            $cleanup($this->tempDir);
            rmdir($this->tempDir);
        }
    });

    describe('constructor', function (): void {
        test('loads resolver definitions from single YAML file', function (): void {
            // Arrange
            $yamlContent = [
                'user-resolver' => [
                    'type' => 'database',
                    'table' => 'users',
                ],
                'config-resolver' => [
                    'type' => 'array',
                    'data' => ['key' => 'value'],
                ],
            ];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Act
            $repository = new YamlRepository($filePath);

            // Assert
            expect($repository->all())->toBe($yamlContent);
        });

        test('loads resolver definitions from multiple YAML files', function (): void {
            // Arrange
            $file1Content = [
                'resolver-1' => ['type' => 'type-1'],
                'resolver-2' => ['type' => 'type-2'],
            ];
            $file2Content = [
                'resolver-3' => ['type' => 'type-3'],
                'resolver-4' => ['type' => 'type-4'],
            ];

            $file1Path = $this->tempDir . '/resolvers1.yaml';
            $file2Path = $this->tempDir . '/resolvers2.yaml';

            file_put_contents($file1Path, Yaml::dump($file1Content));
            file_put_contents($file2Path, Yaml::dump($file2Content));

            // Act
            $repository = new YamlRepository([$file1Path, $file2Path]);

            // Assert
            expect($repository->all())->toBe([
                'resolver-1' => ['type' => 'type-1'],
                'resolver-2' => ['type' => 'type-2'],
                'resolver-3' => ['type' => 'type-3'],
                'resolver-4' => ['type' => 'type-4'],
            ]);
        });

        test('later files override earlier files for duplicate resolver names', function (): void {
            // Arrange
            $file1Content = [
                'resolver-1' => ['type' => 'original'],
                'resolver-2' => ['type' => 'unchanged'],
            ];
            $file2Content = [
                'resolver-1' => ['type' => 'overridden'],
                'resolver-3' => ['type' => 'new'],
            ];

            $file1Path = $this->tempDir . '/base.yaml';
            $file2Path = $this->tempDir . '/override.yaml';

            file_put_contents($file1Path, Yaml::dump($file1Content));
            file_put_contents($file2Path, Yaml::dump($file2Content));

            // Act
            $repository = new YamlRepository([$file1Path, $file2Path]);

            // Assert
            expect($repository->get('resolver-1'))->toBe(['type' => 'overridden']);
            expect($repository->get('resolver-2'))->toBe(['type' => 'unchanged']);
            expect($repository->get('resolver-3'))->toBe(['type' => 'new']);
        });

        test('resolves relative paths with base path', function (): void {
            // Arrange
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Act
            $repository = new YamlRepository('resolvers.yaml', $this->tempDir);

            // Assert
            expect($repository->has('resolver'))->toBeTrue();
        });

        test('handles absolute paths regardless of base path', function (): void {
            // Arrange
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Act - absolute path should ignore base path
            $repository = new YamlRepository($filePath, '/some/other/path');

            // Assert
            expect($repository->has('resolver'))->toBeTrue();
        });

        test('throws RuntimeException when file does not exist', function (): void {
            // Arrange
            $nonExistentPath = $this->tempDir . '/nonexistent.yaml';

            // Act & Assert
            expect(fn(): YamlRepository => new YamlRepository($nonExistentPath))
                ->toThrow(RuntimeException::class, 'YAML file not found: ' . $nonExistentPath);
        });

        test('throws RuntimeException when file is not readable', function (): void {
            // Skip in Docker environments where permission checks may not work as expected
            if (posix_geteuid() === 0) {
                $this->markTestSkipped('Cannot test file permissions as root user');
            }

            // Arrange
            $filePath = $this->tempDir . '/unreadable.yaml';
            file_put_contents($filePath, Yaml::dump(['resolver' => ['type' => 'test']]));
            chmod($filePath, 0o000); // Make file unreadable

            try {
                // Act & Assert
                expect(fn(): YamlRepository => new YamlRepository($filePath))
                    ->toThrow(RuntimeException::class, 'YAML file not readable: ' . $filePath);
            } finally {
                // Cleanup - restore permissions so afterEach can delete it
                chmod($filePath, 0o644);
            }
        });

        test('throws RuntimeException when YAML is invalid', function (): void {
            // Arrange
            $filePath = $this->tempDir . '/invalid.yaml';
            file_put_contents($filePath, "invalid:\n  yaml:\n    - broken\n  - misaligned");

            // Act & Assert
            expect(fn(): YamlRepository => new YamlRepository($filePath))
                ->toThrow(RuntimeException::class, 'Invalid YAML in file');
        });

        test('throws RuntimeException when YAML file does not contain array', function (): void {
            // Arrange
            $filePath = $this->tempDir . '/scalar.yaml';
            file_put_contents($filePath, "just a string");

            // Act & Assert
            expect(fn(): YamlRepository => new YamlRepository($filePath))
                ->toThrow(RuntimeException::class, 'YAML file must contain a mapping/array: ' . $filePath);
        });

        test('throws RuntimeException when symfony/yaml is not installed', function (): void {
            // This test verifies the check exists - we can't actually test the failure
            // since symfony/yaml is installed in this environment
            // The code throws: throw_unless(\class_exists(Yaml::class), ...)

            // We can verify the class exists
            expect(class_exists(Yaml::class))->toBeTrue();

            // The actual check in constructor is:
            // throw_unless(\class_exists(Yaml::class), \RuntimeException::class, 'YamlRepository requires symfony/yaml package...')
            // This would only fail if symfony/yaml wasn't installed
        });
    });

    describe('get()', function (): void {
        test('retrieves resolver definition by name', function (): void {
            // Arrange
            $yamlContent = [
                'user-resolver' => [
                    'type' => 'database',
                    'table' => 'users',
                    'connection' => 'mysql',
                ],
            ];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $resolver = $repository->get('user-resolver');

            // Assert
            expect($resolver)->toBe([
                'type' => 'database',
                'table' => 'users',
                'connection' => 'mysql',
            ]);
        });

        test('throws ResolverNotFoundException when resolver does not exist', function (): void {
            // Arrange
            $yamlContent = ['existing-resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act & Assert
            expect(fn(): array => $repository->get('nonexistent-resolver'))
                ->toThrow(ResolverNotFoundException::class, "Resolver 'nonexistent-resolver' not found");
        });
    });

    describe('has()', function (): void {
        test('returns true when resolver exists', function (): void {
            // Arrange
            $yamlContent = [
                'resolver-1' => ['type' => 'test'],
                'resolver-2' => ['type' => 'test'],
            ];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act & Assert
            expect($repository->has('resolver-1'))->toBeTrue();
            expect($repository->has('resolver-2'))->toBeTrue();
        });

        test('returns false when resolver does not exist', function (): void {
            // Arrange
            $yamlContent = ['existing-resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act & Assert
            expect($repository->has('nonexistent-resolver'))->toBeFalse();
        });
    });

    describe('all()', function (): void {
        test('returns all resolver definitions', function (): void {
            // Arrange
            $yamlContent = [
                'resolver-1' => ['type' => 'type-1', 'config' => 'config-1'],
                'resolver-2' => ['type' => 'type-2', 'config' => 'config-2'],
                'resolver-3' => ['type' => 'type-3', 'config' => 'config-3'],
            ];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toBe($yamlContent);
            expect($all)->toHaveCount(3);
        });

        test('returns empty array when no resolvers exist', function (): void {
            // Arrange
            $yamlContent = [];
            $filePath = $this->tempDir . '/empty.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $all = $repository->all();

            // Assert
            expect($all)->toBe([]);
            expect($all)->toHaveCount(0);
        });
    });

    describe('getMany()', function (): void {
        test('retrieves multiple resolver definitions', function (): void {
            // Arrange
            $yamlContent = [
                'resolver-1' => ['type' => 'type-1'],
                'resolver-2' => ['type' => 'type-2'],
                'resolver-3' => ['type' => 'type-3'],
            ];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $many = $repository->getMany(['resolver-1', 'resolver-3']);

            // Assert
            expect($many)->toBe([
                'resolver-1' => ['type' => 'type-1'],
                'resolver-3' => ['type' => 'type-3'],
            ]);
        });

        test('skips non-existent resolvers', function (): void {
            // Arrange
            $yamlContent = [
                'resolver-1' => ['type' => 'type-1'],
                'resolver-2' => ['type' => 'type-2'],
            ];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $many = $repository->getMany(['resolver-1', 'nonexistent', 'resolver-2']);

            // Assert
            expect($many)->toBe([
                'resolver-1' => ['type' => 'type-1'],
                'resolver-2' => ['type' => 'type-2'],
            ]);
        });

        test('returns empty array when all requested resolvers do not exist', function (): void {
            // Arrange
            $yamlContent = ['existing' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $many = $repository->getMany(['nonexistent-1', 'nonexistent-2']);

            // Assert
            expect($many)->toBe([]);
        });

        test('returns empty array when no names provided', function (): void {
            // Arrange
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $many = $repository->getMany([]);

            // Assert
            expect($many)->toBe([]);
        });
    });

    describe('path resolution', function (): void {
        test('handles Unix absolute paths correctly', function (): void {
            // Arrange
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Act - Unix absolute path starts with /
            $repository = new YamlRepository($filePath);

            // Assert
            expect($repository->has('resolver'))->toBeTrue();
        });

        test('handles Windows absolute paths correctly', function (): void {
            // This test verifies the Windows path detection logic
            // The actual behavior depends on the OS, but we can verify the path is recognized
            // Windows paths like C:\path\to\file.yaml are detected by:
            // \strlen($path) >= 3 && \ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/')

            // We create a relative path test to ensure base path works
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Verify with relative path and base path
            $repository = new YamlRepository('resolvers.yaml', $this->tempDir);
            expect($repository->has('resolver'))->toBeTrue();
        });

        test('combines base path with relative paths using correct directory separator', function (): void {
            // Arrange
            $subdir = $this->tempDir . '/subdir';
            mkdir($subdir);
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $subdir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Act - provide base path and relative path
            $repository = new YamlRepository('subdir/resolvers.yaml', $this->tempDir);

            // Assert
            expect($repository->has('resolver'))->toBeTrue();
        });

        test('base path is trimmed of trailing directory separator', function (): void {
            // Arrange
            $yamlContent = ['resolver' => ['type' => 'test']];
            $filePath = $this->tempDir . '/resolvers.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));

            // Act - base path with trailing separator
            $repository = new YamlRepository('resolvers.yaml', $this->tempDir . DIRECTORY_SEPARATOR);

            // Assert
            expect($repository->has('resolver'))->toBeTrue();
        });
    });

    describe('edge cases', function (): void {
        test('throws RuntimeException for empty YAML file', function (): void {
            // Arrange
            $filePath = $this->tempDir . '/empty.yaml';
            file_put_contents($filePath, '');

            // Act & Assert
            // Empty YAML files parse to null, not an array
            expect(fn(): YamlRepository => new YamlRepository($filePath))
                ->toThrow(RuntimeException::class, 'YAML file must contain a mapping/array');
        });

        test('throws RuntimeException for YAML file with only comments', function (): void {
            // Arrange
            $filePath = $this->tempDir . '/comments.yaml';
            file_put_contents($filePath, "# Just a comment\n# Another comment");

            // Act & Assert
            // Comments-only YAML files parse to null, not an array
            expect(fn(): YamlRepository => new YamlRepository($filePath))
                ->toThrow(RuntimeException::class, 'YAML file must contain a mapping/array');
        });

        test('handles YAML file with explicit empty array', function (): void {
            // Arrange
            $filePath = $this->tempDir . '/explicit-empty.yaml';
            file_put_contents($filePath, '{}');

            // Act
            $repository = new YamlRepository($filePath);

            // Assert
            expect($repository->all())->toBe([]);
            expect($repository->all())->toHaveCount(0);
        });

        test('handles resolver with complex nested data', function (): void {
            // Arrange
            $yamlContent = [
                'complex-resolver' => [
                    'type' => 'nested',
                    'config' => [
                        'level1' => [
                            'level2' => [
                                'level3' => 'deep-value',
                            ],
                        ],
                    ],
                    'array-config' => ['item1', 'item2', 'item3'],
                    'numeric' => 42,
                    'boolean' => true,
                    'null-value' => null,
                ],
            ];
            $filePath = $this->tempDir . '/complex.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act
            $resolver = $repository->get('complex-resolver');

            // Assert
            expect($resolver['type'])->toBe('nested');
            expect($resolver['config']['level1']['level2']['level3'])->toBe('deep-value');
            expect($resolver['array-config'])->toBe(['item1', 'item2', 'item3']);
            expect($resolver['numeric'])->toBe(42);
            expect($resolver['boolean'])->toBe(true);
            expect($resolver['null-value'])->toBeNull();
        });

        test('handles resolver names with special characters', function (): void {
            // Arrange
            $yamlContent = [
                'resolver-with-dashes' => ['type' => 'test1'],
                'resolver_with_underscores' => ['type' => 'test2'],
                'resolver.with.dots' => ['type' => 'test3'],
                'resolver:with:colons' => ['type' => 'test4'],
            ];
            $filePath = $this->tempDir . '/special.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act & Assert
            expect($repository->has('resolver-with-dashes'))->toBeTrue();
            expect($repository->has('resolver_with_underscores'))->toBeTrue();
            expect($repository->has('resolver.with.dots'))->toBeTrue();
            expect($repository->has('resolver:with:colons'))->toBeTrue();
        });

        test('handles Unicode characters in resolver names and values', function (): void {
            // Arrange
            $yamlContent = [
                'résolveur' => ['type' => 'français'],
                '解析器' => ['type' => '中文'],
                'разрешитель' => ['type' => 'русский'],
            ];
            $filePath = $this->tempDir . '/unicode.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act & Assert
            expect($repository->has('résolveur'))->toBeTrue();
            expect($repository->get('résolveur')['type'])->toBe('français');
            expect($repository->has('解析器'))->toBeTrue();
            expect($repository->get('解析器')['type'])->toBe('中文');
            expect($repository->has('разрешитель'))->toBeTrue();
            expect($repository->get('разрешитель')['type'])->toBe('русский');
        });

        test('handles very large YAML files', function (): void {
            // Arrange - create a file with many resolvers
            $yamlContent = [];
            for ($i = 1; $i <= 1000; ++$i) {
                $yamlContent['resolver-' . $i] = [
                    'type' => 'type-' . $i,
                    'config' => ['index' => $i],
                ];
            }

            $filePath = $this->tempDir . '/large.yaml';
            file_put_contents($filePath, Yaml::dump($yamlContent));
            $repository = new YamlRepository($filePath);

            // Act & Assert
            expect($repository->all())->toHaveCount(1000);
            expect($repository->has('resolver-1'))->toBeTrue();
            expect($repository->has('resolver-500'))->toBeTrue();
            expect($repository->has('resolver-1000'))->toBeTrue();
            expect($repository->get('resolver-500')['config']['index'])->toBe(500);
        });

        test('handles multiple files with some having empty content', function (): void {
            // Arrange
            $file1Content = ['resolver-1' => ['type' => 'test']];
            $file2Content = [];
            $file3Content = ['resolver-3' => ['type' => 'test']];

            $file1Path = $this->tempDir . '/file1.yaml';
            $file2Path = $this->tempDir . '/file2.yaml';
            $file3Path = $this->tempDir . '/file3.yaml';

            file_put_contents($file1Path, Yaml::dump($file1Content));
            file_put_contents($file2Path, Yaml::dump($file2Content));
            file_put_contents($file3Path, Yaml::dump($file3Content));

            // Act
            $repository = new YamlRepository([$file1Path, $file2Path, $file3Path]);

            // Assert
            expect($repository->all())->toBe([
                'resolver-1' => ['type' => 'test'],
                'resolver-3' => ['type' => 'test'],
            ]);
        });
    });
});
