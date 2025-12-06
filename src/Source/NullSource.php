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
 * Source that always returns null.
 *
 * Useful for testing resolution chains and ensuring proper
 * fallback behavior when no value is found.
 * @author Brian Faust <brian@cline.sh>
 */
final class NullSource extends AbstractSource
{
    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Always returns null regardless of key or context.
     */
    public function get(string $key, array $context): mixed
    {
        return null;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * @return array<string, mixed>
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
