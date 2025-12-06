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
 * Thrown when resolution fails because no sources are available.
 * @author Brian Faust <brian@cline.sh>
 */
final class NoSourcesAvailableException extends CascadeException
{
    public static function forKey(string $key): self
    {
        return new self(
            sprintf("Failed to resolve '%s'. No sources available.", $key),
        );
    }
}
