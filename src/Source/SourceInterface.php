<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

/**
 * Contract for cascade resolution sources.
 *
 * Sources provide values during the cascade resolution process and can
 * implement conditional logic to determine whether they support specific
 * key/context combinations. Implementations should be stateless and thread-safe.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface SourceInterface
{
    /**
     * Get the unique identifier for this source.
     *
     * Used for logging, debugging, and identifying sources in the resolution
     * chain. Should return a consistent, human-readable name.
     *
     * @return string Unique source identifier
     */
    public function getName(): string;

    /**
     * Check if this source supports the given key/context combination.
     *
     * Allows sources to opt out of resolution for specific keys or contexts,
     * enabling conditional source activation based on runtime conditions.
     *
     * @param  string               $key     The configuration key to check
     * @param  array<string, mixed> $context Runtime context for conditional logic
     * @return bool                 True if this source should attempt resolution, false to skip
     */
    public function supports(string $key, array $context): bool;

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Implementations should return null if the value cannot be found,
     * allowing the cascade to continue to the next source in the chain.
     *
     * @param  string               $key     The configuration key to resolve
     * @param  array<string, mixed> $context Additional context for resolution
     * @return mixed                The resolved value, or null if not found
     */
    public function get(string $key, array $context): mixed;

    /**
     * Get metadata about this source for debugging and logging.
     *
     * Should return structured information about the source's configuration,
     * type, and any relevant runtime state for debugging purposes.
     *
     * @return array<string, mixed> Source metadata including type and configuration
     */
    public function getMetadata(): array;
}
