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
 * Exception thrown when attempting to register a source with a duplicate name.
 *
 * Source names must be unique within a resolver to ensure proper identification
 * during resolution and debugging. This exception prevents accidental overwrites
 * or conflicts between sources.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DuplicateSourceNameException extends SourceException
{
    /**
     * Create exception for duplicate source name.
     *
     * @param string $name The source name that is already registered
     *
     * @return self Exception instance with descriptive message
     */
    public static function forName(string $name): self
    {
        return new self(
            sprintf("Source with name '%s' is already registered.", $name),
        );
    }
}
