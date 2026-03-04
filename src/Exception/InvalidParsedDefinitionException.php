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
 * Exception thrown when a parsed resolver definition is not an array or object.
 *
 * After parsing a JSON string or processing a definition, the result must be
 * an array or object structure that can be used for resolver configuration.
 * This exception is thrown when the parsed result is a scalar value (string,
 * integer, boolean, etc.) instead of the expected array/object structure.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidParsedDefinitionException extends CascadeException
{
    /**
     * Create exception for invalid parsed definition structure.
     *
     * @param string $name The resolver name with an invalid parsed definition structure
     *
     * @return self Exception instance with descriptive error message
     */
    public static function forResolver(string $name): self
    {
        return new self(
            sprintf("Invalid definition for resolver '%s': expected object/array", $name),
        );
    }
}
