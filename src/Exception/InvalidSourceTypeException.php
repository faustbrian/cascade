<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use function implode;
use function sprintf;

/**
 * Thrown when a source has an invalid type.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSourceTypeException extends SourceException
{
    /**
     * @param string        $type       The invalid type provided
     * @param array<string> $validTypes List of valid types
     */
    public static function forType(string $type, array $validTypes): self
    {
        $valid = implode(', ', $validTypes);

        return new self(
            sprintf("Invalid source type '%s'. Valid types are: %s", $type, $valid),
        );
    }
}
