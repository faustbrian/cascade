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
 * Event fired when a source is queried (before get() is called).
 *
 * @internal
 */
final readonly class SourceQueried extends CascadeEvent
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        /**
         * The name of the source being queried.
         */
        public string $sourceName,
        /**
         * The key being resolved.
         */
        public string $key,
        /**
         * The resolution context.
         */
        public array $context,
    ) {}
}
