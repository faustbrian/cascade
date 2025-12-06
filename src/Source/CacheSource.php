<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 cache decorator for any source.
 *
 * Wraps another source and caches resolved values to improve performance.
 * Uses the decorator pattern to add caching behavior transparently.
 */
final readonly class CacheSource implements SourceInterface
{
    /**
     * @param string $name Unique identifier for this source
     * @param SourceInterface $inner The source to wrap with caching
     * @param CacheInterface $cache PSR-16 cache implementation
     * @param int $ttl Time-to-live in seconds
     * @param callable(string, array<string, mixed>): string|null $keyGenerator Optional cache key generator
     */
    public function __construct(
        private string $name,
        private SourceInterface $inner,
        private CacheInterface $cache,
        private int $ttl = 300,
        private mixed $keyGenerator = null,
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
     * Delegates to the wrapped source.
     */
    public function supports(string $key, array $context): bool
    {
        return $this->inner->supports($key, $context);
    }

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Checks cache first, then falls back to the wrapped source.
     * Caches non-null values for future requests.
     */
    public function get(string $key, array $context): mixed
    {
        $cacheKey = $this->generateCacheKey($key, $context);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $value = $this->inner->get($key, $context);
        if ($value !== null) {
            $this->cache->set($cacheKey, $value, $this->ttl);
        }

        return $value;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'type' => 'cache',
            'ttl' => $this->ttl,
            'inner' => $this->inner->getName(),
            'has_key_generator' => $this->keyGenerator !== null,
        ];
    }

    /**
     * Generate a cache key for the given key and context.
     *
     * @param array<string, mixed> $context
     */
    private function generateCacheKey(string $key, array $context): string
    {
        if ($this->keyGenerator !== null) {
            return ($this->keyGenerator)($key, $context);
        }

        return 'cascade:' . md5($key . serialize($context));
    }
}
