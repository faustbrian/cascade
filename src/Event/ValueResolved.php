<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Event;

/**
 * Event fired when a value is successfully resolved from a source.
 *
 * Captures successful resolution outcomes including the resolved value, the source
 * that provided it, and performance timing information. Useful for monitoring,
 * debugging, and performance analysis of configuration resolution.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final readonly class ValueResolved extends CascadeEvent
{
    /**
     * Create a new value resolved event.
     *
     * @param string               $key        Configuration key that was successfully resolved
     * @param mixed                $value      The resolved value (before transformers are applied)
     * @param string               $sourceName Name of the source that provided the value,
     *                                         indicating which source in the chain succeeded
     * @param float                $durationMs Total resolution duration in milliseconds,
     *                                         measured from the start of resolution to completion
     * @param array<string, mixed> $context    Resolution context data used during resolution,
     *                                         containing interpolation values and metadata
     */
    public function __construct(
        public string $key,
        public mixed $value,
        public string $sourceName,
        public float $durationMs,
        public array $context,
    ) {}
}
