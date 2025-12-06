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
 * Thrown when a source name is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSourceNameException extends SourceException
{
    public static function forName(string $name): self
    {
        return new self(
            sprintf("Invalid source name '%s'. Source names must be non-empty strings.", $name),
        );
    }
}
