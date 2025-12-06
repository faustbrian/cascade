<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Cline\Cascade\Exception\CascadeException;
use Cline\Cascade\Exception\ResolutionFailedException;

describe('ResolutionFailedException', function (): void {
    describe('exception hierarchy', function (): void {
        test('extends CascadeException', function (): void {
            // Arrange & Act
            $exception = ResolutionFailedException::forKey('test', ['source1']);

            // Assert
            expect($exception)->toBeInstanceOf(CascadeException::class);
        });

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = ResolutionFailedException::forKey('test', ['source1']);

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('forKey()', function (): void {
        test('creates exception with single attempted source', function (): void {
            // Arrange
            $key = 'api.key';
            $sources = ['env-source'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve 'api.key'. Attempted sources: env-source");
        });

        test('creates exception with multiple attempted sources', function (): void {
            // Arrange
            $key = 'database.host';
            $sources = ['env-source', 'config-source', 'default-source'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve 'database.host'. Attempted sources: env-source, config-source, default-source");
        });

        test('handles empty sources array', function (): void {
            // Arrange
            $key = 'missing.key';
            $sources = [];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve 'missing.key'. Attempted sources: ");
        });

        test('handles special characters in key', function (): void {
            // Arrange
            $key = 'config.nested[0].value@special';
            $sources = ['source1'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toContain('config.nested[0].value@special');
        });

        test('handles unicode characters in key', function (): void {
            // Arrange
            $key = 'clé_français';
            $sources = ['source1'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toContain('clé_français');
        });

        test('handles empty string key', function (): void {
            // Arrange
            $key = '';
            $sources = ['source1'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve ''. Attempted sources: source1");
        });

        test('handles sources with special characters', function (): void {
            // Arrange
            $key = 'test.key';
            $sources = ['env-source', 'config_source', 'source.with.dots'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toContain('env-source, config_source, source.with.dots');
        });

        test('handles many attempted sources', function (): void {
            // Arrange
            $key = 'config.key';
            $sources = ['source1', 'source2', 'source3', 'source4', 'source5', 'source6'];

            // Act
            $exception = ResolutionFailedException::forKey($key, $sources);

            // Assert
            expect($exception->getMessage())->toContain('source1, source2, source3, source4, source5, source6');
        });
    });

    describe('noSourcesAvailable()', function (): void {
        test('creates exception with no sources available message', function (): void {
            // Arrange
            $key = 'api.token';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve 'api.token'. No sources available.");
        });

        test('handles simple key', function (): void {
            // Arrange
            $key = 'key';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve 'key'. No sources available.");
        });

        test('handles dotted key', function (): void {
            // Arrange
            $key = 'database.connection.host';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve 'database.connection.host'. No sources available.");
        });

        test('handles special characters in key', function (): void {
            // Arrange
            $key = 'config[nested].value@special';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toContain('config[nested].value@special');
            expect($exception->getMessage())->toContain('No sources available');
        });

        test('handles unicode characters in key', function (): void {
            // Arrange
            $key = '配置_キー';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toContain('配置_キー');
        });

        test('handles empty string key', function (): void {
            // Arrange
            $key = '';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toBe("Failed to resolve ''. No sources available.");
        });

        test('handles long key', function (): void {
            // Arrange
            $key = 'very.long.nested.configuration.key.that.might.be.used.in.some.cases';

            // Act
            $exception = ResolutionFailedException::noSourcesAvailable($key);

            // Assert
            expect($exception->getMessage())->toContain($key);
            expect($exception->getMessage())->toContain('No sources available');
        });

        test('returns ResolutionFailedException instance', function (): void {
            // Arrange & Act
            $exception = ResolutionFailedException::noSourcesAvailable('test.key');

            // Assert
            expect($exception)->toBeInstanceOf(ResolutionFailedException::class);
        });
    });

    describe('exception message format', function (): void {
        test('forKey message contains key and sources', function (): void {
            // Arrange & Act
            $exception = ResolutionFailedException::forKey('my.key', ['source1', 'source2']);

            // Assert
            expect($exception->getMessage())
                ->toContain('my.key')
                ->toContain('source1')
                ->toContain('source2')
                ->toContain('Failed to resolve')
                ->toContain('Attempted sources');
        });

        test('noSourcesAvailable message contains key and no sources text', function (): void {
            // Arrange & Act
            $exception = ResolutionFailedException::noSourcesAvailable('my.key');

            // Assert
            expect($exception->getMessage())
                ->toContain('my.key')
                ->toContain('Failed to resolve')
                ->toContain('No sources available');
        });

        test('noSourcesAvailable does not mention attempted sources', function (): void {
            // Arrange & Act
            $exception = ResolutionFailedException::noSourcesAvailable('test.key');

            // Assert
            expect($exception->getMessage())->not->toContain('Attempted sources');
        });
    });
});
