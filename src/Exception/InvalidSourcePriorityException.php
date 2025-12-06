<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use function get_debug_type;
use function sprintf;

/**
 * Thrown when a source priority is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSourcePriorityException extends SourceException
{
    public static function forValue(mixed $priority): self
    {
        $type = get_debug_type($priority);

        return new self(
            sprintf('Invalid source priority. Expected integer, got %s.', $type),
        );
    }
}
