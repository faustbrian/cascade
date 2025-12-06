<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Thrown when a value cannot be resolved from any source.
 */
final class ResolutionFailedException extends CascadeException
{
    /**
     * Create exception for failed resolution.
     *
     * @param string $key The key that failed to resolve
     * @param array<string> $attemptedSources List of sources that were tried
     */
    public static function forKey(string $key, array $attemptedSources): self
    {
        $sources = \implode(', ', $attemptedSources);

        return new self(
            sprintf("Failed to resolve '%s'. Attempted sources: %s", $key, $sources),
        );
    }

    /**
     * Create exception for failed resolution with no attempted sources.
     */
    public static function noSourcesAvailable(string $key): self
    {
        return new self(
            sprintf("Failed to resolve '%s'. No sources available.", $key),
        );
    }
}
