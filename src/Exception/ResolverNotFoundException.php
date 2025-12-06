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
 * Thrown when a named resolver cannot be found.
 * @author Brian Faust <brian@cline.sh>
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
}
