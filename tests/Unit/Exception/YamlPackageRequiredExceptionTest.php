<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\YamlPackageRequiredException;

describe('YamlPackageRequiredException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('create()', function (): void {
        test('creates exception with package requirement message', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception->getMessage())->toBe('YamlRepository requires symfony/yaml package. Install it with: composer require symfony/yaml');
        });

        test('message mentions YamlRepository', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception->getMessage())->toContain('YamlRepository');
        });

        test('message mentions symfony/yaml package', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception->getMessage())->toContain('symfony/yaml');
        });

        test('message includes installation instructions', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception->getMessage())->toContain('composer require symfony/yaml');
        });

        test('message includes "Install it with" instruction phrase', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception->getMessage())->toContain('Install it with:');
        });

        test('returns instance of YamlPackageRequiredException', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception)->toBeInstanceOf(YamlPackageRequiredException::class);
        });

        test('message format is helpful and actionable', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            $message = $exception->getMessage();
            expect($message)->toContain('requires');
            expect($message)->toContain('package');
            expect($message)->toContain('composer');
        });

        test('creates new instance on each call', function (): void {
            // Arrange & Act
            $exception1 = YamlPackageRequiredException::create();
            $exception2 = YamlPackageRequiredException::create();

            // Assert
            expect($exception1)->not->toBe($exception2);
            expect($exception1->getMessage())->toBe($exception2->getMessage());
        });
    });

    describe('edge cases', function (): void {
        test('exception can be thrown and caught', function (): void {
            // Arrange
            $caught = false;

            // Act
            try {
                throw YamlPackageRequiredException::create();
            } catch (YamlPackageRequiredException) {
                $caught = true;
            }

            // Assert
            expect($caught)->toBeTrue();
        });

        test('exception can be caught as CascadeException', function (): void {
            // Arrange
            $caught = false;

            // Act
            try {
                throw YamlPackageRequiredException::create();
            } catch (CascadeException) {
                $caught = true;
            }

            // Assert
            expect($caught)->toBeTrue();
        });

        test('exception can be caught as RuntimeException', function (): void {
            // Arrange
            $caught = false;

            // Act
            try {
                throw YamlPackageRequiredException::create();
            } catch (RuntimeException) {
                $caught = true;
            }

            // Assert
            expect($caught)->toBeTrue();
        });

        test('message is not empty', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            expect($exception->getMessage())->not->toBeEmpty();
        });

        test('message does not contain placeholder text', function (): void {
            // Arrange & Act
            $exception = YamlPackageRequiredException::create();

            // Assert
            $message = $exception->getMessage();
            expect($message)->not->toContain('TODO');
            expect($message)->not->toContain('FIXME');
            expect($message)->not->toContain('XXX');
            expect($message)->not->toContain('%s');
            expect($message)->not->toContain('{{');
        });
    });
});
