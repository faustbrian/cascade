<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use RuntimeException;

/**
 * Base exception for all Cascade library errors.
 *
 * Provides a common exception type that allows catching all Cascade-specific
 * errors without catching other runtime exceptions. All exceptions thrown by
 * the library extend from this base class.
 *
 * @author Brian Faust <brian@cline.sh>
 * @infection-ignore-all
 */
abstract class CascadeException extends RuntimeException
{
    // Intentionally empty - serves as marker base class for exception hierarchy
}
