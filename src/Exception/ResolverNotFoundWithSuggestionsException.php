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
 * Exception thrown when a named resolver cannot be found, providing helpful suggestions.
 *
 * This exception extends the base ResolverNotFoundException by including a list of
 * available resolvers in the error message, helping developers quickly identify
 * typos or configuration issues.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolverNotFoundWithSuggestionsException extends CascadeException
{
    /**
     * Create exception for resolver not found with available suggestions.
     *
     * Generates a contextual error message that either lists all available resolvers
     * or indicates that no resolvers are currently registered.
     *
     * @param  string        $name               The resolver name that was requested but not found
     * @param  array<string> $availableResolvers List of resolver names currently registered in the system
     * @return self          The configured exception instance with appropriate error message
     */
    public static function forName(string $name, array $availableResolvers): self
    {
        if ($availableResolvers === []) {
            return new self(
                sprintf("Resolver '%s' not found. No resolvers are currently registered.", $name),
            );
        }

        $available = implode(', ', $availableResolvers);

        return new self(
            sprintf("Resolver '%s' not found. Available resolvers: %s", $name, $available),
        );
    }
}
