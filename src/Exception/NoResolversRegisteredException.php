<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when attempting resolution with no registered resolvers.
 *
 * This exception occurs when the resolution system is invoked but no resolvers
 * have been registered to handle value resolution. At least one resolver must
 * be registered before attempting to resolve configuration values. This typically
 * indicates incomplete system initialization or missing resolver registration
 * during application bootstrap.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NoResolversRegisteredException extends CascadeException
{
    /**
     * Create exception indicating that no resolvers are available for resolution.
     */
    public static function create(): self
    {
        return new self(
            'No resolvers are registered. Register at least one resolver before attempting resolution.',
        );
    }
}
