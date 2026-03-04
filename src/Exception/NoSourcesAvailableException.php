<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use function sprintf;

/**
 * Exception thrown when attempting to resolve a value but no sources are available.
 *
 * This exception occurs when the resolution system attempts to resolve a configuration
 * key but finds no sources registered or enabled. Unlike NoResolversRegisteredException
 * (no resolvers at all), this indicates that while resolvers may exist, no actual
 * data sources are configured or available for querying. This typically happens when
 * all sources are disabled, misconfigured, or not properly registered.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NoSourcesAvailableException extends CascadeException
{
    /**
     * Create exception for a key that cannot be resolved due to lack of sources.
     *
     * @param string $key The configuration key that attempted resolution with no available sources
     */
    public static function forKey(string $key): self
    {
        return new self(
            sprintf("Failed to resolve '%s'. No sources available.", $key),
        );
    }
}
