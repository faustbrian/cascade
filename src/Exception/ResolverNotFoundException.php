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
 * Exception thrown when attempting to retrieve a resolver that is not registered.
 *
 * This exception occurs when code attempts to access a specific resolver by name
 * but that resolver has not been registered in the resolver registry. This typically
 * indicates a configuration error, typo in the resolver name, or missing resolver
 * registration during system initialization. Resolvers must be explicitly registered
 * before they can be retrieved and used.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolverNotFoundException extends CascadeException
{
    /**
     * Create exception for a named resolver that cannot be found in the registry.
     *
     * @param string $name The resolver name that was requested but not found in the registered resolvers
     */
    public static function forName(string $name): self
    {
        return new self(
            sprintf("Resolver '%s' not found. Ensure the resolver is registered.", $name),
        );
    }
}
