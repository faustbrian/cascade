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
 * Exception thrown when a source priority value has an invalid type.
 *
 * Source priorities determine the order in which sources are consulted during
 * value resolution. Priorities must be integer values to allow proper sorting
 * and comparison. This exception prevents registration of sources with non-integer
 * priorities that would cause type errors during source ordering operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSourcePriorityException extends SourceException
{
    /**
     * Create exception for invalid source priority type.
     *
     * @param mixed $priority The invalid priority value that was provided (non-integer type)
     *
     * @return self Exception instance with type information for debugging
     */
    public static function forValue(mixed $priority): self
    {
        $type = get_debug_type($priority);

        return new self(
            sprintf('Invalid source priority. Expected integer, got %s.', $type),
        );
    }
}
