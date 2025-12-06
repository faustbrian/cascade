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
 * Thrown when a named resolver cannot be found.
 */
final class ResolverNotFoundException extends CascadeException
{
    /**
     * Create exception for resolver not found by name.
     *
     * @param string $name The resolver name that was not found
     */
    public static function forName(string $name): self
    {
        return new self(
            sprintf("Resolver '%s' not found. Ensure the resolver is registered.", $name),
        );
    }

    /**
     * Create exception for resolver not found with suggestions.
     *
     * @param string $name The resolver name that was not found
     * @param array<string> $availableResolvers List of available resolver names
     */
    public static function withSuggestions(string $name, array $availableResolvers): self
    {
        if ($availableResolvers === []) {
            return new self(
                sprintf("Resolver '%s' not found. No resolvers are currently registered.", $name),
            );
        }

        $available = \implode(', ', $availableResolvers);

        return new self(
            sprintf("Resolver '%s' not found. Available resolvers: %s", $name, $available),
        );
    }

    /**
     * Create exception for no resolvers registered.
     */
    public static function noResolversRegistered(): self
    {
        return new self(
            'No resolvers are registered. Register at least one resolver before attempting resolution.',
        );
    }
}
