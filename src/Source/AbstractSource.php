<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

/**
 * Base implementation for cascade sources.
 *
 * Provides default implementations for common source methods that can be overridden
 * by specific source types. Handles source naming and provides sensible defaults for
 * supports() and getMetadata() methods, reducing boilerplate in concrete implementations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class AbstractSource implements SourceInterface
{
    /**
     * Create a new source with a unique name.
     *
     * @param string $name Unique identifier for this source. Used for debugging, logging,
     *                     and identifying which source provided a value during resolution.
     */
    public function __construct(
        protected readonly string $name,
    ) {}

    /**
     * Get the unique name of this source.
     *
     * @return string The unique identifier for this source
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this source supports the given key/context combination.
     *
     * Default implementation returns true for all keys/contexts, meaning the source
     * will be queried for every resolution. Override this method to implement conditional
     * source logic based on key patterns, context values, or other criteria.
     *
     * @param  string               $key     The key being resolved
     * @param  array<string, mixed> $context Additional context for resolution
     * @return bool                 True if this source should be queried for the key/context, false to skip
     */
    public function supports(string $key, array $context): bool
    {
        return true;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * Default implementation returns basic source information including name and type.
     * Override this method to include source-specific metadata such as cache statistics,
     * connection details, or other debugging information.
     *
     * @return array<string, mixed> Metadata array containing at minimum the source name and type
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'type' => static::class,
        ];
    }
}
