<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Thrown when no resolvers are registered in the system.
 * @author Brian Faust <brian@cline.sh>
 */
final class NoResolversRegisteredException extends CascadeException
{
    public static function create(): self
    {
        return new self(
            'No resolvers are registered. Register at least one resolver before attempting resolution.',
        );
    }
}
