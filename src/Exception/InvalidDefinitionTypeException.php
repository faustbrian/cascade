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
 * Exception thrown when a resolver definition has an invalid type.
 *
 * Resolver definitions must be either a JSON string or an array structure.
 * This exception is thrown when a definition is provided in an unsupported
 * format, preventing proper resolver configuration and initialization.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDefinitionTypeException extends CascadeException
{
    /**
     * Create exception for invalid resolver definition type.
     *
     * @param string $name The resolver name that has an invalid definition type
     *
     * @return self Exception instance with descriptive error message
     */
    public static function forResolver(string $name): self
    {
        return new self(
            sprintf("Invalid definition for resolver '%s': expected JSON string or array", $name),
        );
    }
}
