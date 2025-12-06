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
 * Provides values from a predefined array. Useful for defaults,
 * configuration values, or testing.
 * @author Brian Faust <brian@cline.sh>
 */
final class ArraySource extends AbstractSource
{
    /**
     * @param string               $name   Unique identifier for this source
     * @param array<string, mixed> $values Key-value pairs to resolve from
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
     * Returns the value if the key exists in the array, null otherwise.
     * Context is not used for array sources.
     */
    public function get(string $key, array $context): mixed
    {
        return $this->values[$key] ?? null;
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
            'type' => 'array',
            'key_count' => count($this->values),
            'keys' => array_keys($this->values),
        ];
    }
}
