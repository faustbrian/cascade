<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Event;

/**
 * Event fired when resolution fails across all configured sources.
 *
 * Captures information about failed resolution attempts, including the requested
 * key, all sources that were queried, and the resolution context. Useful for
 * debugging missing configuration values and understanding resolution flow.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final readonly class ResolutionFailed extends CascadeEvent
{
    /**
     * Create a new resolution failed event.
     *
     * @param string               $key              Configuration key that failed to resolve
     * @param array<string>        $attemptedSources Names of all sources queried during resolution,
     *                                               ordered by priority from highest (lowest number)
     *                                               to lowest (highest number)
     * @param array<string, mixed> $context          Resolution context data used during the attempt,
     *                                               containing interpolation values and metadata
     */
    public function __construct(
        public string $key,
        public array $attemptedSources,
        public array $context,
    ) {}
}
