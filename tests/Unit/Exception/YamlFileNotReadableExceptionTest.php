<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\YamlFileNotReadableException;

describe('YamlFileNotReadableException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = YamlFileNotReadableException::atPath('/path/to/file.yaml');

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = YamlFileNotReadableException::atPath('/path/to/file.yaml');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('atPath()', function (): void {
        test('creates exception with file path in message', function (): void {
            // Arrange
            $path = '/var/www/config/database.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: /var/www/config/database.yaml');
        });

        test('handles relative paths', function (): void {
            // Arrange
            $path = './config/app.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: ./config/app.yaml');
        });

        test('handles absolute paths with multiple directories', function (): void {
            // Arrange
            $path = '/usr/local/share/cascade/resolvers/custom.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: /usr/local/share/cascade/resolvers/custom.yaml');
        });

        test('handles paths with spaces', function (): void {
            // Arrange
            $path = '/path/to/my config/file.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/to/my config/file.yaml');
        });

        test('handles paths with special characters', function (): void {
            // Arrange
            $path = '/path/to/config-file_v2.0.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/to/config-file_v2.0.yaml');
        });

        test('handles paths with unicode characters', function (): void {
            // Arrange
            $path = '/path/to/configuración.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/to/configuración.yaml');
        });

        test('handles Windows-style paths', function (): void {
            // Arrange
            $path = 'C:\\Users\\Admin\\config\\database.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: C:\\Users\\Admin\\config\\database.yaml');
        });

        test('handles paths with dots', function (): void {
            // Arrange
            $path = '/path/to/../config/file.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/to/../config/file.yaml');
        });

        test('handles paths with tilde', function (): void {
            // Arrange
            $path = '~/config/cascade.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: ~/config/cascade.yaml');
        });

        test('handles empty path', function (): void {
            // Arrange
            $path = '';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: ');
        });

        test('handles file with .yml extension', function (): void {
            // Arrange
            $path = '/config/settings.yml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/config/settings.yml');
        });

        test('handles file without extension', function (): void {
            // Arrange
            $path = '/config/settings';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('YAML file not readable: /config/settings');
        });

        test('handles very long paths', function (): void {
            // Arrange
            $path = '/very/long/path/with/many/nested/directories/that/goes/on/and/on/config.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
        });

        test('message starts with expected prefix', function (): void {
            // Arrange
            $path = '/any/path.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toStartWith('YAML file not readable: ');
        });

        test('is throwable', function (): void {
            // Arrange
            $path = '/config/readonly.yaml';

            // Act & Assert
            expect(fn () => throw YamlFileNotReadableException::atPath($path))
                ->toThrow(YamlFileNotReadableException::class);
        });

        test('preserves exact path in message', function (): void {
            // Arrange
            $path = '/path/./to/../nested/./file.yaml';

            // Act
            $exception = YamlFileNotReadableException::atPath($path);

            // Assert
            // The exception should preserve the exact path as provided, not normalize it
            expect($exception->getMessage())->toBe('YAML file not readable: /path/./to/../nested/./file.yaml');
        });
    });
});
