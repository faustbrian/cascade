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
 * Thrown when a value cannot be resolved from any attempted source.
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolutionFailedForKeyException extends CascadeException
{
    /**
     * @param string        $key              The key that failed to resolve
     * @param array<string> $attemptedSources List of sources that were tried
     */
    public static function withAttemptedSources(string $key, array $attemptedSources): self
    {
        $sources = implode(', ', $attemptedSources);

        return new self(
            sprintf("Failed to resolve '%s'. Attempted sources: %s", $key, $sources),
        );
    }
}
