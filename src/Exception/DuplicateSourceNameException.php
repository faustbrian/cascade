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
 * Thrown when a source with a duplicate name is registered.
 * @author Brian Faust <brian@cline.sh>
 */
final class DuplicateSourceNameException extends SourceException
{
    public static function forName(string $name): self
    {
        return new self(
            sprintf("Source with name '%s' is already registered.", $name),
        );
    }
}
