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
 * Event fired when resolution fails across all sources.
 *
 * @internal
 */
final readonly class ResolutionFailed extends CascadeEvent
{
    /**
     * @param array<string>        $attemptedSources
     * @param array<string, mixed> $context
     */
    public function __construct(
        /**
         * The key that failed to resolve.
         */
        public string $key,
        /**
         * The names of all sources that were attempted.
         */
        public array $attemptedSources,
        /**
         * The resolution context.
         */
        public array $context,
    ) {}
}
