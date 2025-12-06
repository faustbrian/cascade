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
 * Thrown when a named resolver cannot be found, with suggestions for available resolvers.
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolverNotFoundWithSuggestionsException extends CascadeException
{
    /**
     * Create exception for resolver not found with available suggestions.
     *
     * @param string        $name               The resolver name that was not found
     * @param array<string> $availableResolvers List of available resolver names
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
