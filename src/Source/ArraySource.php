<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

use Override;

use function array_keys;
use function count;

/**
 * Static array-based source for simple key-value resolution.
 *
 * Provides values from a predefined array. Useful for default values, configuration
 * overrides, testing scenarios, or any situation where values are known at construction
 * time. The context parameter is ignored during resolution.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ArraySource extends AbstractSource
{
    /**
     * Create a new array source with predefined values.
     *
     * @param string               $name   Unique identifier for this source. Used for debugging
     *                                     and identifying which source provided a value.
     * @param array<string, mixed> $values Key-value pairs to resolve from. Keys should match
     *                                     the keys that will be requested during resolution.
     */
    public function __construct(
        string $name,
        private readonly array $values,
    ) {
        parent::__construct($name);
    }

    /**
     * Attempt to resolve a value for the given key.
     *
     * Performs a simple array lookup. Returns the value if the key exists in the array,
     * or null otherwise. The context parameter is ignored as array sources provide static
     * values that don't vary based on context.
     *
     * @param  string               $key     The key to resolve
     * @param  array<string, mixed> $context Additional context (unused for array sources)
     * @return mixed                The value for the key if it exists, or null if not found
     */
    public function get(string $key, array $context): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * Includes the source type, number of keys available, and list of all keys.
     * Useful for understanding what values are available from this source.
     *
     * @return array<string, mixed> Metadata including type, key count, and available keys
     */
    #[Override()]
    public function getMetadata(): array
    {
        return [
            ...parent::getMetadata(),
            'type' => 'array',
            'key_count' => count($this->values),
            'keys' => array_keys($this->values),
        ];
    }
}
