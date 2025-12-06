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
 * Creates a sub-cascade from multiple sources, allowing for
 * hierarchical resolution chains. Useful for grouping related
 * sources into a single logical source.
 * @author Brian Faust <brian@cline.sh>
 */
final class ChainedSource extends AbstractSource
{
    /**
     * @param string                 $name    Unique identifier for this source
     * @param array<SourceInterface> $sources Ordered list of sources to chain
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
     * Returns true if any chained source supports the key/context.
     */
    #[Override()]
    public function supports(string $key, array $context): bool
    {
        return array_any($this->sources, fn ($source): bool => $source->supports($key, $context));
    }

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Tries each source in order until a non-null value is found.
     * Returns null if no source provides a value.
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
     * @return array<string, mixed>
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
