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
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
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
