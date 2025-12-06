<?php

declare(strict_types=1);

/**
 * Copyright (C) Cline - All Rights Reserved
 *
 * @see       https://cline.sh
 * @author    Brian Faust <brian@cline.sh>
 * @license   MIT
 */

namespace Cline\Cascade\Event;

/**
 * Event fired when a value is successfully resolved from a source.
 *
 * @internal
 */
final readonly class ValueResolved extends CascadeEvent
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        /**
         * The key that was resolved.
         */
        public string $key,
        /**
         * The resolved value.
         */
        public mixed $value,
        /**
         * The name of the source that provided the value.
         */
        public string $sourceName,
        /**
         * The time taken to resolve the value in milliseconds.
         */
        public float $durationMs,
        /**
         * The resolution context.
         */
        public array $context,
    ) {}
}
