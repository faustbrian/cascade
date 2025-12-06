<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Event;

/**
 * Event fired when a source is queried (before get() is called).
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
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
