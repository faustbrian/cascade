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
 * Exception thrown when a source name is invalid or empty.
 *
 * Source names are used to identify and distinguish between different configuration
 * sources within a resolver. They must be non-empty strings to ensure proper source
 * identification during resolution and debugging. This exception prevents registration
 * of sources with invalid names that would cause lookup failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSourceNameException extends SourceException
{
    /**
     * Create exception for invalid source name.
     *
     * @param string $name The invalid source name that was provided (may be empty)
     *
     * @return self Exception instance with descriptive error message
     */
    public static function forName(string $name): self
    {
        return new self(
            sprintf("Invalid source name '%s'. Source names must be non-empty strings.", $name),
        );
    }
}
