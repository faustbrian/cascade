<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use Throwable;

use function sprintf;

/**
 * Exception thrown when a resolver definition contains invalid JSON syntax.
 *
 * This exception occurs during resolver configuration when a JSON-formatted
 * definition string cannot be parsed due to syntax errors, malformed structure,
 * or encoding issues. The exception includes the underlying JSON parsing error
 * message to aid in debugging the definition format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonDefinitionException extends CascadeException
{
    /**
     * Create exception for invalid JSON in resolver definition.
     *
     * @param string         $name         The resolver name with invalid JSON definition
     * @param string         $errorMessage The JSON parsing error message describing the syntax issue
     * @param null|Throwable $previous     Optional previous exception that caused the JSON parsing failure
     *
     * @return self Exception instance with detailed error context
     */
    public static function forResolver(string $name, string $errorMessage, ?Throwable $previous = null): self
    {
        return new self(
            sprintf("Invalid JSON definition for resolver '%s': %s", $name, $errorMessage),
            0,
            $previous,
        );
    }
}
