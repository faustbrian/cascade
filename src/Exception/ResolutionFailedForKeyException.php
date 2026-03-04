<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use function implode;
use function sprintf;

/**
 * Exception thrown when a configuration key cannot be resolved from any attempted source.
 *
 * This exception occurs during the cascade resolution process when all available
 * sources have been queried for a specific key but none could provide a value.
 * The exception includes diagnostic information listing all sources that were
 * attempted, helping developers identify configuration gaps or missing values
 * across their source hierarchy.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolutionFailedForKeyException extends CascadeException
{
    /**
     * Create exception with detailed information about failed resolution attempt.
     *
     * @param string             $key              The configuration key that could not be resolved from any source
     * @param array<int, string> $attemptedSources List of source names that were queried during
     *                                             the cascade resolution process. Provides diagnostic
     *                                             information about which sources were checked before
     *                                             failing, useful for debugging missing configuration.
     */
    public static function withAttemptedSources(string $key, array $attemptedSources): self
    {
        $sources = implode(', ', $attemptedSources);

        return new self(
            sprintf("Failed to resolve '%s'. Attempted sources: %s", $key, $sources),
        );
    }
}
