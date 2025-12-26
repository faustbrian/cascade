<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

use Override;

/**
 * Source implementation that always returns null values.
 *
 * Provides a null-object pattern implementation for the source chain,
 * useful for testing resolution chains, ensuring proper fallback behavior,
 * and serving as a safe default when no actual source is available.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NullSource extends AbstractSource
{
    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Always returns null regardless of the provided key or context,
     * effectively skipping this source in the resolution chain.
     *
     * @param string               $key     The configuration key to resolve
     * @param array<string, mixed> $context Additional context for resolution
     */
    public function get(string $key, array $context): mixed
    {
        return null;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * Extends parent metadata with the null source type identifier,
     * allowing debugging tools to identify this as a null source.
     *
     * @return array<string, mixed> Metadata including source type 'null'
     */
    #[Override()]
    public function getMetadata(): array
    {
        return [
            ...parent::getMetadata(),
            'type' => 'null',
        ];
    }
}
