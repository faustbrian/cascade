<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\ResolverNotFoundException;
use Psr\SimpleCache\CacheInterface;

use function is_array;

/**
 * Repository decorator that adds PSR-16 caching to any repository implementation.
 *
 * This caching layer wraps another repository and stores resolver definitions in
 * a PSR-16 cache to reduce expensive operations like database queries or file I/O.
 * Individual cached entries can be invalidated when resolvers are updated, and
 * the entire cache can be flushed when needed.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CachedRepository implements ResolverRepositoryInterface
{
    /**
     * Create a new caching repository wrapper.
     *
     * @param ResolverRepositoryInterface $inner  The underlying repository to wrap with caching.
     *                                            All cache misses are forwarded to this repository.
     * @param CacheInterface              $cache  PSR-16 SimpleCache implementation used to store
     *                                            cached resolver definitions.
     * @param null|int                    $ttl    Time-to-live for cached entries in seconds. Null means
     *                                            entries never expire. Use finite TTL if resolvers change
     *                                            frequently or to prevent stale cache issues.
     * @param string                      $prefix Cache key prefix to namespace resolver entries and prevent
     *                                            collisions with other cached data in shared cache pools.
     */
    public function __construct(
        private ResolverRepositoryInterface $inner,
        private CacheInterface $cache,
        private ?int $ttl = null,
        private string $prefix = 'cascade:resolvers:',
    ) {}

    /**
     * Retrieve a resolver definition by name with caching.
     *
     * First checks the cache for a previously stored definition. On cache miss,
     * fetches from the inner repository and stores the result in cache for future requests.
     *
     * @param  string                    $name The resolver identifier to retrieve
     * @throws ResolverNotFoundException When the resolver is not found in the inner repository
     * @return array<string, mixed>      The resolver's configuration definition
     */
    public function get(string $name): array
    {
        $cacheKey = $this->getCacheKey($name);

        // Try to get from cache first
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        // Fetch from inner repository
        $definition = $this->inner->get($name);

        // Store in cache
        $this->cache->set($cacheKey, $definition, $this->ttl);

        return $definition;
    }

    /**
     * Check whether a resolver definition exists.
     *
     * Checks the cache first for performance, then queries the inner repository
     * if not cached.
     *
     * @param  string $name The resolver identifier to check
     * @return bool   True if the resolver exists, false otherwise
     */
    public function has(string $name): bool
    {
        $cacheKey = $this->getCacheKey($name);

        // If it's in cache, it exists
        if ($this->cache->has($cacheKey)) {
            return true;
        }

        // Check inner repository
        return $this->inner->has($name);
    }

    /**
     * Retrieve all resolver definitions without caching.
     *
     * This method always fetches directly from the inner repository and does not
     * cache the complete result set, as caching all definitions at once is typically
     * inefficient and unnecessary for most use cases.
     *
     * @return array<string, array<string, mixed>> Complete map of resolver names to their definitions
     */
    public function all(): array
    {
        // Don't cache all() results as it's typically only called once at initialization
        return $this->inner->all();
    }

    /**
     * Retrieve multiple resolver definitions with intelligent caching.
     *
     * Optimizes performance by first checking the cache for each requested resolver.
     * Any cache misses are batch-fetched from the inner repository and stored in
     * cache for subsequent requests.
     *
     * @param  array<string>                       $names List of resolver identifiers to retrieve
     * @return array<string, array<string, mixed>> Map of found resolver names to their definitions
     */
    public function getMany(array $names): array
    {
        /** @var array<string, array<string, mixed>> */
        $result = [];
        $uncached = [];

        // Try to get each from cache
        foreach ($names as $name) {
            $cacheKey = $this->getCacheKey($name);
            $cached = $this->cache->get($cacheKey);

            if (is_array($cached)) {
                /** @var array<string, mixed> $cached */
                $result[$name] = $cached;
            } else {
                $uncached[] = $name;
            }
        }

        // Fetch uncached definitions from inner repository
        if ($uncached !== []) {
            $fetched = $this->inner->getMany($uncached);

            // Store fetched definitions in cache
            foreach ($fetched as $name => $definition) {
                $cacheKey = $this->getCacheKey($name);
                $this->cache->set($cacheKey, $definition, $this->ttl);
                $result[$name] = $definition;
            }
        }

        return $result;
    }

    /**
     * Remove a cached resolver definition from the cache.
     *
     * Invalidates the cached entry for the specified resolver. The next request
     * for this resolver will fetch fresh data from the inner repository and
     * re-cache the result.
     *
     * @param  string $name The resolver identifier to remove from cache
     * @return bool   True if the cache entry was successfully deleted, false if it didn't exist
     */
    public function forget(string $name): bool
    {
        $cacheKey = $this->getCacheKey($name);

        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear all cached resolver definitions from the cache pool.
     *
     * WARNING: This clears the entire cache pool using PSR-16's clear() method,
     * not just resolver entries. If the cache instance is shared with other
     * application data, use a dedicated cache pool for resolvers to avoid
     * unintended data loss.
     *
     * @return bool True if the cache was successfully cleared, false otherwise
     */
    public function flush(): bool
    {
        // Note: PSR-16 doesn't support clearing by prefix, so this clears the entire cache
        // In production, use a dedicated cache pool for resolver definitions
        return $this->cache->clear();
    }

    /**
     * Generate a namespaced cache key for a resolver identifier.
     *
     * Combines the configured prefix with the resolver name to create a unique
     * cache key that prevents collisions in shared cache pools.
     *
     * @param  string $name The resolver identifier
     * @return string The prefixed cache key for storage
     */
    private function getCacheKey(string $name): string
    {
        return $this->prefix.$name;
    }
}
