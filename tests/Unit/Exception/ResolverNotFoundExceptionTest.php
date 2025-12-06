<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\ResolverNotFoundException;

describe('ResolverNotFoundException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = ResolverNotFoundException::forName('test');

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = ResolverNotFoundException::forName('test');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('forName()', function (): void {
        test('creates exception with resolver not found message', function (): void {
            // Arrange
            $name = 'primary-resolver';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toBe("Resolver 'primary-resolver' not found. Ensure the resolver is registered.");
        });

        test('handles resolver name with hyphens', function (): void {
            // Arrange
            $name = 'my-custom-resolver';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toContain('my-custom-resolver');
            expect($exception->getMessage())->toContain('Ensure the resolver is registered');
        });

        test('handles resolver name with underscores', function (): void {
            // Arrange
            $name = 'my_custom_resolver';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toContain('my_custom_resolver');
        });

        test('handles resolver name with dots', function (): void {
            // Arrange
            $name = 'resolver.config.primary';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toContain('resolver.config.primary');
        });

        test('handles empty string resolver name', function (): void {
            // Arrange
            $name = '';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toBe("Resolver '' not found. Ensure the resolver is registered.");
        });

        test('handles unicode characters in resolver name', function (): void {
            // Arrange
            $name = 'résolveur-français';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toContain('résolveur-français');
        });

        test('handles special characters in resolver name', function (): void {
            // Arrange
            $name = '@resolver#123';

            // Act
            $exception = ResolverNotFoundException::forName($name);

            // Assert
            expect($exception->getMessage())->toContain('@resolver#123');
        });
    });

    describe('withSuggestions()', function (): void {
        test('creates exception with available resolvers when suggestions provided', function (): void {
            // Arrange
            $name = 'missing-resolver';
            $availableResolvers = ['resolver-one', 'resolver-two', 'resolver-three'];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toBe("Resolver 'missing-resolver' not found. Available resolvers: resolver-one, resolver-two, resolver-three");
        });

        test('creates exception with no resolvers message when empty array provided', function (): void {
            // Arrange
            $name = 'any-resolver';
            $availableResolvers = [];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toBe("Resolver 'any-resolver' not found. No resolvers are currently registered.");
        });

        test('handles single available resolver', function (): void {
            // Arrange
            $name = 'nonexistent';
            $availableResolvers = ['primary'];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toBe("Resolver 'nonexistent' not found. Available resolvers: primary");
        });

        test('handles many available resolvers', function (): void {
            // Arrange
            $name = 'missing';
            $availableResolvers = ['resolver-1', 'resolver-2', 'resolver-3', 'resolver-4', 'resolver-5', 'resolver-6'];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toContain('Available resolvers: resolver-1, resolver-2, resolver-3, resolver-4, resolver-5, resolver-6');
        });

        test('handles resolver names with various naming conventions', function (): void {
            // Arrange
            $name = 'test';
            $availableResolvers = ['camelCase', 'PascalCase', 'snake_case', 'kebab-case', 'dot.case'];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toContain('camelCase, PascalCase, snake_case, kebab-case, dot.case');
        });

        test('handles unicode resolver names in suggestions', function (): void {
            // Arrange
            $name = 'test';
            $availableResolvers = ['résolveur', 'решатель', '解决者'];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toContain('résolveur, решатель, 解决者');
        });

        test('handles empty string in resolver name', function (): void {
            // Arrange
            $name = '';
            $availableResolvers = ['resolver-1', 'resolver-2'];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toBe("Resolver '' not found. Available resolvers: resolver-1, resolver-2");
        });

        test('handles empty string resolver name with no suggestions', function (): void {
            // Arrange
            $name = '';
            $availableResolvers = [];

            // Act
            $exception = ResolverNotFoundException::withSuggestions($name, $availableResolvers);

            // Assert
            expect($exception->getMessage())->toBe("Resolver '' not found. No resolvers are currently registered.");
        });
    });

    describe('noResolversRegistered()', function (): void {
        test('creates exception with no resolvers registered message', function (): void {
            // Arrange & Act
            $exception = ResolverNotFoundException::noResolversRegistered();

            // Assert
            expect($exception->getMessage())->toBe('No resolvers are registered. Register at least one resolver before attempting resolution.');
        });

        test('returns instance of ResolverNotFoundException', function (): void {
            // Arrange & Act
            $exception = ResolverNotFoundException::noResolversRegistered();

            // Assert
            expect($exception)->toBeInstanceOf(ResolverNotFoundException::class);
        });

        test('message contains instruction to register resolver', function (): void {
            // Arrange & Act
            $exception = ResolverNotFoundException::noResolversRegistered();

            // Assert
            expect($exception->getMessage())->toContain('Register at least one resolver');
        });
    });
});
