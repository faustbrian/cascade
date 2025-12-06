<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\InvalidSourceException;
use Cline\Cascade\Exception\SourceException;

describe('InvalidSourceException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends SourceException', function (): void {
            // Arrange & Act
            $exception = InvalidSourceException::missingConfiguration('test');

            // Assert
            expect($exception)->toBeInstanceOf(SourceException::class);
        });

        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = InvalidSourceException::missingConfiguration('test');

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = InvalidSourceException::missingConfiguration('test');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('missingConfiguration()', function (): void {
        test('creates exception with missing configuration message', function (): void {
            // Arrange
            $key = 'api_key';

            // Act
            $exception = InvalidSourceException::missingConfiguration($key);

            // Assert
            expect($exception->getMessage())->toBe("Source configuration is missing required key: 'api_key'");
        });

        test('handles special characters in configuration key', function (): void {
            // Arrange
            $key = 'config.nested[0].value';

            // Act
            $exception = InvalidSourceException::missingConfiguration($key);

            // Assert
            expect($exception->getMessage())->toContain($key);
        });

        test('handles unicode characters in configuration key', function (): void {
            // Arrange
            $key = 'clé_français';

            // Act
            $exception = InvalidSourceException::missingConfiguration($key);

            // Assert
            expect($exception->getMessage())->toContain($key);
        });

        test('handles empty string configuration key', function (): void {
            // Arrange
            $key = '';

            // Act
            $exception = InvalidSourceException::missingConfiguration($key);

            // Assert
            expect($exception->getMessage())->toBe("Source configuration is missing required key: ''");
        });
    });

    describe('invalidType()', function (): void {
        test('creates exception with invalid type and valid types list', function (): void {
            // Arrange
            $invalidType = 'unknown';
            $validTypes = ['array', 'callback', 'null'];

            // Act
            $exception = InvalidSourceException::invalidType($invalidType, $validTypes);

            // Assert
            expect($exception->getMessage())->toBe("Invalid source type 'unknown'. Valid types are: array, callback, null");
        });

        test('handles single valid type', function (): void {
            // Arrange
            $invalidType = 'redis';
            $validTypes = ['array'];

            // Act
            $exception = InvalidSourceException::invalidType($invalidType, $validTypes);

            // Assert
            expect($exception->getMessage())->toBe("Invalid source type 'redis'. Valid types are: array");
        });

        test('handles empty valid types array', function (): void {
            // Arrange
            $invalidType = 'invalid';
            $validTypes = [];

            // Act
            $exception = InvalidSourceException::invalidType($invalidType, $validTypes);

            // Assert
            expect($exception->getMessage())->toBe("Invalid source type 'invalid'. Valid types are: ");
        });

        test('handles many valid types', function (): void {
            // Arrange
            $invalidType = 'unknown';
            $validTypes = ['array', 'callback', 'null', 'database', 'redis', 'file'];

            // Act
            $exception = InvalidSourceException::invalidType($invalidType, $validTypes);

            // Assert
            expect($exception->getMessage())->toContain('array, callback, null, database, redis, file');
        });

        test('handles special characters in type names', function (): void {
            // Arrange
            $invalidType = 'custom-type';
            $validTypes = ['type-1', 'type_2', 'Type.3'];

            // Act
            $exception = InvalidSourceException::invalidType($invalidType, $validTypes);

            // Assert
            expect($exception->getMessage())->toContain('custom-type');
            expect($exception->getMessage())->toContain('type-1, type_2, Type.3');
        });
    });

    describe('invalidName()', function (): void {
        test('creates exception with invalid name message', function (): void {
            // Arrange
            $name = '';

            // Act
            $exception = InvalidSourceException::invalidName($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid source name ''. Source names must be non-empty strings.");
        });

        test('handles whitespace-only name', function (): void {
            // Arrange
            $name = '   ';

            // Act
            $exception = InvalidSourceException::invalidName($name);

            // Assert
            expect($exception->getMessage())->toContain('   ');
            expect($exception->getMessage())->toContain('Source names must be non-empty strings');
        });

        test('handles special characters in name', function (): void {
            // Arrange
            $name = '@#$%^&*';

            // Act
            $exception = InvalidSourceException::invalidName($name);

            // Assert
            expect($exception->getMessage())->toContain('@#$%^&*');
        });

        test('handles numeric string name', function (): void {
            // Arrange
            $name = '12345';

            // Act
            $exception = InvalidSourceException::invalidName($name);

            // Assert
            expect($exception->getMessage())->toContain('12345');
        });
    });

    describe('duplicateName()', function (): void {
        test('creates exception with duplicate name message', function (): void {
            // Arrange
            $name = 'primary-source';

            // Act
            $exception = InvalidSourceException::duplicateName($name);

            // Assert
            expect($exception->getMessage())->toBe("Source with name 'primary-source' is already registered.");
        });

        test('handles names with hyphens', function (): void {
            // Arrange
            $name = 'my-custom-source';

            // Act
            $exception = InvalidSourceException::duplicateName($name);

            // Assert
            expect($exception->getMessage())->toContain('my-custom-source');
        });

        test('handles names with underscores', function (): void {
            // Arrange
            $name = 'my_custom_source';

            // Act
            $exception = InvalidSourceException::duplicateName($name);

            // Assert
            expect($exception->getMessage())->toContain('my_custom_source');
        });

        test('handles names with dots', function (): void {
            // Arrange
            $name = 'source.config.primary';

            // Act
            $exception = InvalidSourceException::duplicateName($name);

            // Assert
            expect($exception->getMessage())->toContain('source.config.primary');
        });
    });

    describe('invalidPriority()', function (): void {
        test('creates exception for string priority', function (): void {
            // Arrange
            $priority = 'high';

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got string.');
        });

        test('creates exception for array priority', function (): void {
            // Arrange
            $priority = [1, 2, 3];

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got array.');
        });

        test('creates exception for null priority', function (): void {
            // Arrange
            $priority = null;

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got null.');
        });

        test('creates exception for boolean priority', function (): void {
            // Arrange
            $priority = true;

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got bool.');
        });

        test('creates exception for float priority', function (): void {
            // Arrange
            $priority = 3.14;

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got float.');
        });

        test('creates exception for object priority', function (): void {
            // Arrange
            $priority = new stdClass();

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got stdClass.');
        });

        test('creates exception for anonymous class priority', function (): void {
            // Arrange
            $priority = new class {};

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toContain('Invalid source priority. Expected integer, got class@anonymous');
        });

        test('creates exception for closure priority', function (): void {
            // Arrange
            $priority = fn(): int => 5;

            // Act
            $exception = InvalidSourceException::invalidPriority($priority);

            // Assert
            expect($exception->getMessage())->toBe('Invalid source priority. Expected integer, got Closure.');
        });
    });
});
