<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\JsonFileReadFailedException;

describe('JsonFileReadFailedException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = JsonFileReadFailedException::atPath('/path/to/file.json');

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = JsonFileReadFailedException::atPath('/path/to/file.json');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('atPath()', function (): void {
        test('creates exception with failed to read message', function (): void {
            // Arrange
            $path = '/var/www/config/resolver.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('Failed to read JSON file: /var/www/config/resolver.json');
        });

        test('includes file path in message', function (): void {
            // Arrange
            $path = '/path/to/file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/to/file.json');
            expect($exception->getMessage())->toContain('Failed to read JSON file');
        });

        test('handles absolute unix paths', function (): void {
            // Arrange
            $path = '/usr/local/etc/cascade/config.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/usr/local/etc/cascade/config.json');
        });

        test('handles absolute windows paths', function (): void {
            // Arrange
            $path = 'C:\\Program Files\\Cascade\\config.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('C:\\Program Files\\Cascade\\config.json');
        });

        test('handles relative paths', function (): void {
            // Arrange
            $path = '../config/resolver.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('../config/resolver.json');
        });

        test('handles paths with spaces', function (): void {
            // Arrange
            $path = '/path/with spaces/in the name/file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/with spaces/in the name/file.json');
        });

        test('handles paths with unicode characters', function (): void {
            // Arrange
            $path = '/путь/к/файлу/config.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/путь/к/файлу/config.json');
        });

        test('handles paths with special characters', function (): void {
            // Arrange
            $path = '/path/@special/file#1.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/@special/file#1.json');
        });

        test('handles empty path', function (): void {
            // Arrange
            $path = '';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('Failed to read JSON file: ');
        });

        test('handles very long paths', function (): void {
            // Arrange
            $path = '/very/'.str_repeat('long/', 100).'path/to/file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain($path);
            expect($exception->getMessage())->toStartWith('Failed to read JSON file: ');
        });

        test('handles paths with multiple consecutive slashes', function (): void {
            // Arrange
            $path = '/path//to///file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path//to///file.json');
        });

        test('handles paths with dots representing current and parent directories', function (): void {
            // Arrange
            $path = '/path/./to/../file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/./to/../file.json');
        });

        test('handles network paths', function (): void {
            // Arrange
            $path = '\\\\network\\share\\config\\file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('\\\\network\\share\\config\\file.json');
        });

        test('handles file URI schemes', function (): void {
            // Arrange
            $path = 'file:///path/to/file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('file:///path/to/file.json');
        });

        test('handles paths with emoji characters', function (): void {
            // Arrange
            $path = '/path/to/📁config/file.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('/path/to/📁config/file.json');
        });

        test('message format is consistent', function (): void {
            // Arrange
            $paths = [
                '/simple/path.json',
                'C:\\windows\\path.json',
                '../relative/path.json',
                '/path/with spaces/file.json',
            ];

            foreach ($paths as $path) {
                // Act
                $exception = JsonFileReadFailedException::atPath($path);

                // Assert
                expect($exception->getMessage())->toStartWith('Failed to read JSON file: ');
                expect($exception->getMessage())->toEndWith($path);
            }
        });
    });

    describe('system-level failure scenarios', function (): void {
        test('exception indicates I/O failure distinct from permissions', function (): void {
            // Arrange
            $path = '/dev/full';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toBe('Failed to read JSON file: /dev/full');
            // Verify message does NOT contain permission-related wording
            expect($exception->getMessage())->not->toContain('permission');
            expect($exception->getMessage())->not->toContain('readable');
        });

        test('exception indicates read failure distinct from not found', function (): void {
            // Arrange
            $path = '/mnt/failing-disk/config.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            expect($exception->getMessage())->toContain('Failed to read');
            // Verify message does NOT contain not-found wording
            expect($exception->getMessage())->not->toContain('not found');
            expect($exception->getMessage())->not->toContain('does not exist');
        });

        test('represents file_get_contents returning false', function (): void {
            // Arrange
            $path = '/tmp/io-error-simulation.json';

            // Act
            $exception = JsonFileReadFailedException::atPath($path);

            // Assert
            // This exception should be thrown when file_get_contents() === false
            // which indicates system-level failures like:
            // - Disk I/O errors
            // - Network filesystem failures
            // - Resource exhaustion during read
            expect($exception->getMessage())->toBe('Failed to read JSON file: /tmp/io-error-simulation.json');
        });
    });

    describe('real-world path scenarios', function (): void {
        test('handles typical Linux config paths', function (): void {
            // Arrange
            $paths = [
                '/etc/cascade/config.json',
                '/var/lib/cascade/resolvers.json',
                '/usr/local/share/cascade/data.json',
                '/home/user/.config/cascade/settings.json',
            ];

            foreach ($paths as $path) {
                // Act
                $exception = JsonFileReadFailedException::atPath($path);

                // Assert
                expect($exception->getMessage())->toContain($path);
            }
        });

        test('handles typical macOS paths', function (): void {
            // Arrange
            $paths = [
                '/Users/username/Library/Application Support/Cascade/config.json',
                '/Library/Application Support/Cascade/config.json',
                '/private/var/cascade/data.json',
            ];

            foreach ($paths as $path) {
                // Act
                $exception = JsonFileReadFailedException::atPath($path);

                // Assert
                expect($exception->getMessage())->toContain($path);
            }
        });

        test('handles typical Windows paths', function (): void {
            // Arrange
            $paths = [
                'C:\\ProgramData\\Cascade\\config.json',
                'C:\\Users\\Username\\AppData\\Local\\Cascade\\settings.json',
                'D:\\Data\\cascade\\resolvers.json',
            ];

            foreach ($paths as $path) {
                // Act
                $exception = JsonFileReadFailedException::atPath($path);

                // Assert
                expect($exception->getMessage())->toContain($path);
            }
        });

        test('handles Docker volume paths', function (): void {
            // Arrange
            $paths = [
                '/var/lib/docker/volumes/cascade/_data/config.json',
                '/mnt/volume/cascade/config.json',
            ];

            foreach ($paths as $path) {
                // Act
                $exception = JsonFileReadFailedException::atPath($path);

                // Assert
                expect($exception->getMessage())->toContain($path);
            }
        });
    });
});
