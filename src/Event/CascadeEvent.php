<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Event;

/**
 * Base abstract event class for all Cascade events.
 *
 * Provides a common base type for all events emitted during resolution operations.
 * All events are immutable readonly classes that capture specific moments in the
 * resolution lifecycle for observability and debugging.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract readonly class CascadeEvent
{
    // Intentionally empty - serves as marker base class for type safety
}
