<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\InvalidDefinitionTypeException;

describe('InvalidDefinitionTypeException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = InvalidDefinitionTypeException::forResolver('test-resolver');

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = InvalidDefinitionTypeException::forResolver('test-resolver');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('forResolver()', function (): void {
        test('creates exception with correct message format', function (): void {
            // Arrange
            $name = 'primary-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver 'primary-resolver': expected JSON string or array");
        });

        test('message includes resolver name', function (): void {
            // Arrange
            $name = 'custom-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('custom-resolver');
        });

        test('message specifies expected types', function (): void {
            // Arrange
            $name = 'test';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('JSON string');
            expect($exception->getMessage())->toContain('array');
        });

        test('handles resolver name with hyphens', function (): void {
            // Arrange
            $name = 'my-custom-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('my-custom-resolver');
            expect($exception->getMessage())->toContain('expected JSON string or array');
        });

        test('handles resolver name with underscores', function (): void {
            // Arrange
            $name = 'my_custom_resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('my_custom_resolver');
        });

        test('handles resolver name with dots', function (): void {
            // Arrange
            $name = 'resolver.config.primary';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('resolver.config.primary');
        });

        test('handles empty string resolver name', function (): void {
            // Arrange
            $name = '';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver '': expected JSON string or array");
        });

        test('handles unicode characters in resolver name', function (): void {
            // Arrange
            $name = 'résolveur-français';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('résolveur-français');
        });

        test('handles special characters in resolver name', function (): void {
            // Arrange
            $name = '@resolver#123';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('@resolver#123');
        });

        test('handles long resolver name', function (): void {
            // Arrange
            $name = 'very-long-resolver-name-with-multiple-segments-and-descriptive-parts';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('very-long-resolver-name-with-multiple-segments-and-descriptive-parts');
        });

        test('handles resolver name with numeric suffix', function (): void {
            // Arrange
            $name = 'resolver-v2';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('resolver-v2');
        });
    });

    describe('real-world usage context', function (): void {
        test('matches expected format when definition is integer', function (): void {
            // Arrange
            $name = 'database-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver 'database-resolver': expected JSON string or array");
        });

        test('matches expected format when definition is object', function (): void {
            // Arrange
            $name = 'cache-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver 'cache-resolver': expected JSON string or array");
        });

        test('matches expected format when definition is null', function (): void {
            // Arrange
            $name = 'null-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver 'null-resolver': expected JSON string or array");
        });

        test('matches expected format when definition is boolean', function (): void {
            // Arrange
            $name = 'bool-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver 'bool-resolver': expected JSON string or array");
        });

        test('matches expected format when definition is resource', function (): void {
            // Arrange
            $name = 'resource-resolver';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toBe("Invalid definition for resolver 'resource-resolver': expected JSON string or array");
        });
    });

    describe('edge cases', function (): void {
        test('handles resolver name with only numbers', function (): void {
            // Arrange
            $name = '12345';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('12345');
        });

        test('handles resolver name with whitespace', function (): void {
            // Arrange
            $name = 'resolver with spaces';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('resolver with spaces');
        });

        test('handles resolver name with tabs', function (): void {
            // Arrange
            $name = "resolver\twith\ttabs";

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain("resolver\twith\ttabs");
        });

        test('handles resolver name with newlines', function (): void {
            // Arrange
            $name = "resolver\nwith\nnewlines";

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain("resolver\nwith\nnewlines");
        });

        test('handles resolver name with quotes', function (): void {
            // Arrange
            $name = "resolver'with\"quotes";

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain("resolver'with\"quotes");
        });

        test('handles resolver name with backslashes', function (): void {
            // Arrange
            $name = 'resolver\\with\\backslashes';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('resolver\\with\\backslashes');
        });

        test('handles resolver name with emoji', function (): void {
            // Arrange
            $name = 'resolver-🚀-rocket';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('resolver-🚀-rocket');
        });

        test('handles resolver name with mixed case', function (): void {
            // Arrange
            $name = 'ResolverWithMixedCase';

            // Act
            $exception = InvalidDefinitionTypeException::forResolver($name);

            // Assert
            expect($exception->getMessage())->toContain('ResolverWithMixedCase');
        });
    });
});
