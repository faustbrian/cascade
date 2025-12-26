<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Event;

/**
 * Event fired when a source is queried during resolution.
 *
 * Emitted immediately before a source's `get()` method is invoked, providing
 * visibility into the resolution process. Multiple SourceQueried events may
 * be fired for a single resolution if earlier sources fail to provide a value.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final readonly class SourceQueried extends CascadeEvent
{
    /**
     * Create a new source queried event.
     *
     * @param string               $sourceName Name of the source being queried for resolution
     * @param string               $key        Configuration key being resolved from this source
     * @param array<string, mixed> $context    Resolution context data available to the source,
     *                                         containing interpolation values and metadata
     */
    public function __construct(
        public string $sourceName,
        public string $key,
        public array $context,
    ) {}
}
