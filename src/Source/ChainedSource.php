<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

use Override;

use function array_any;
use function array_map;
use function count;

/**
 * Nested cascade (sub-cascade) source.
 *
 * Creates a sub-cascade from multiple sources, allowing for hierarchical resolution
 * chains. Useful for grouping related sources into a single logical source that can
 * be added to a resolver. Sources are queried in order until one provides a value,
 * implementing a cascade within a cascade for complex resolution strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ChainedSource extends AbstractSource
{
    /**
     * Create a new chained source from multiple sources.
     *
     * @param string                 $name    Unique identifier for this source. Used for debugging
     *                                        and identifying the source in results.
     * @param array<SourceInterface> $sources Ordered list of sources to chain. Sources are queried
     *                                        in array order until one provides a non-null value.
     *                                        Supports nesting other chained sources for deep hierarchies.
     */
    public function __construct(
        string $name,
        private readonly array $sources,
    ) {
        parent::__construct($name);
    }

    /**
     * Check if this source supports the given key/context combination.
     *
     * Returns true if any chained source supports the key/context. Uses short-circuit
     * evaluation to return true as soon as one supporting source is found, avoiding
     * unnecessary checks.
     *
     * @param  string               $key     The key being resolved
     * @param  array<string, mixed> $context Additional context for resolution
     * @return bool                 True if at least one chained source supports the key/context
     */
    #[Override()]
    public function supports(string $key, array $context): bool
    {
        return array_any($this->sources, fn ($source): bool => $source->supports($key, $context));
    }

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Iterates through chained sources in order, querying each source that supports
     * the key/context. Returns the first non-null value found. If no chained source
     * provides a value, returns null.
     *
     * @param  string               $key     The key to resolve
     * @param  array<string, mixed> $context Additional context for resolution
     * @return mixed                The first non-null value from the chained sources, or null if all fail
     */
    public function get(string $key, array $context): mixed
    {
        foreach ($this->sources as $source) {
            if (!$source->supports($key, $context)) {
                continue;
            }

            $value = $source->get($key, $context);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * Includes information about the chained sources such as count and their names.
     * Useful for understanding the composition and structure of the sub-cascade.
     *
     * @return array<string, mixed> Metadata including type, source count, and source names
     */
    #[Override()]
    public function getMetadata(): array
    {
        return [
            ...parent::getMetadata(),
            'type' => 'chained',
            'source_count' => count($this->sources),
            'sources' => array_map(
                fn (SourceInterface $source): string => $source->getName(),
                $this->sources,
            ),
        ];
    }
}
