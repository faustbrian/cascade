<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Event;

/**
 * Event fired when resolution fails across all sources.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
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
