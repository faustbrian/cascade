<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\JsonFileNotReadableException;

describe('JsonFileNotReadableException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = JsonFileNotReadableException::atPath('/path/to/file.json');

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = JsonFileNotReadableException::atPath('/path/to/file.json');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('atPath()', function (): void {
        test('creates exception with file path in message', function (): void {
            // Arrange
            $path = '/var/www/config/settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('JSON file not readable: /var/www/config/settings.json');
        });

        test('includes full absolute path in message', function (): void {
            // Arrange
            $path = '/home/user/project/data/config.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: '.$path);
        });

        test('handles relative paths', function (): void {
            // Arrange
            $path = '../config/settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: ../config/settings.json');
        });

        test('handles paths with spaces', function (): void {
            // Arrange
            $path = '/var/www/my documents/config file.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: /var/www/my documents/config file.json');
        });

        test('handles paths with special characters', function (): void {
            // Arrange
            $path = '/var/www/config-2024/settings_v1.0.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: /var/www/config-2024/settings_v1.0.json');
        });

        test('handles paths with unicode characters', function (): void {
            // Arrange
            $path = '/var/www/données/paramètres.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: /var/www/données/paramètres.json');
        });

        test('handles paths with dots and multiple extensions', function (): void {
            // Arrange
            $path = '/config/file.backup.2024.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: /config/file.backup.2024.json');
        });

        test('handles Windows-style paths', function (): void {
            // Arrange
            $path = 'C:\\Users\\Admin\\config\\settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toBe('JSON file not readable: C:\\Users\\Admin\\config\\settings.json');
        });

        test('handles empty path string', function (): void {
            // Arrange
            $path = '';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('JSON file not readable: ');
        });

        test('handles path with only filename', function (): void {
            // Arrange
            $path = 'config.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('JSON file not readable: config.json');
        });

        test('handles nested directory paths', function (): void {
            // Arrange
            $path = '/var/www/app/storage/data/cache/config/settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
        });

        test('handles paths with parent directory references', function (): void {
            // Arrange
            $path = '/var/www/app/../config/settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
        });

        test('handles paths with current directory references', function (): void {
            // Arrange
            $path = './config/settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('JSON file not readable: ./config/settings.json');
        });

        test('handles paths with tilde for home directory', function (): void {
            // Arrange
            $path = '~/config/settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
        });

        test('handles very long paths', function (): void {
            // Arrange
            $path = '/'.str_repeat('very/long/directory/name/', 20).'config.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toStartWith('JSON file not readable: /');
        });

        test('handles paths with multiple consecutive slashes', function (): void {
            // Arrange
            $path = '/var///www//config///settings.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('JSON file not readable: /var///www//config///settings.json');
        });

        test('handles paths with trailing slash', function (): void {
            // Arrange
            $path = '/var/www/config/';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('JSON file not readable: /var/www/config/');
        });

        test('exception is throwable', function (): void {
            // Arrange
            $path = '/protected/file.json';

            // Act & Assert
            expect(fn () => throw JsonFileNotReadableException::atPath($path))
                ->toThrow(JsonFileNotReadableException::class, 'JSON file not readable: /protected/file.json');
        });

        test('exception message format is consistent', function (): void {
            // Arrange
            $paths = [
                '/var/www/config.json',
                'relative/path.json',
                './current.json',
                '../parent.json',
                '/absolute/path/file.json',
            ];

            // Act & Assert
            foreach ($paths as $path) {
                $exception = JsonFileNotReadableException::atPath($path);
                expect($exception->getMessage())->toBe('JSON file not readable: '.$path);
            }
        });
    });

    describe('real-world usage scenarios', function (): void {
        test('represents permission denied error scenario', function (): void {
            // Arrange - Simulating when file exists but lacks read permissions (chmod 000)
            $path = '/var/www/storage/protected.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception)->toBeInstanceOf(JsonFileNotReadableException::class);
            expect($exception->getMessage())->toContain($path);
        });

        test('represents SELinux/AppArmor blocked file scenario', function (): void {
            // Arrange - Simulating security policy blocking access
            $path = '/restricted/security-policy-blocked.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception)->toBeInstanceOf(JsonFileNotReadableException::class);
            expect($exception->getMessage())->toContain('security-policy-blocked.json');
        });

        test('represents file owned by different user scenario', function (): void {
            // Arrange - Simulating file owned by root with restrictive permissions
            $path = '/root/.config/app.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($path);

            // Assert
            expect($exception)->toBeInstanceOf(JsonFileNotReadableException::class);
            expect($exception->getMessage())->toContain('root');
        });

        test('distinct from file not found exception', function (): void {
            // Arrange - This is for when file EXISTS but cannot be READ
            $existingButUnreadablePath = '/var/www/chmod-000.json';

            // Act
            $exception = JsonFileNotReadableException::atPath($existingButUnreadablePath);

            // Assert
            expect($exception->getMessage())->toContain('not readable');
            expect($exception->getMessage())->not->toContain('not found');
        });
    });
});
