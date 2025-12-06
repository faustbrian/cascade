<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

/**
 * Base implementation for cascade sources.
 *
 * Provides default implementations for common source methods
 * that can be overridden by specific source types.
 */
abstract class AbstractSource implements SourceInterface
{
    /**
     * @param string $name Unique identifier for this source
     */
    public function __construct(
        protected readonly string $name,
    ) {}

    /**
     * Get the unique name of this source.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this source supports the given key/context combination.
     *
     * Default implementation returns true for all keys/contexts.
     * Override to implement conditional source logic.
     */
    public function supports(string $key, array $context): bool
    {
        return true;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * Default implementation returns basic source information.
     * Override to include source-specific metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'type' => static::class,
        ];
    }
}
