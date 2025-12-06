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
 * Wrap any repository with PSR-16 caching.
 *
 * Caches individual resolver definitions to reduce database queries or
 * file I/O operations. Provides methods to invalidate cached entries.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CachedRepository implements ResolverRepositoryInterface
{
    /**
     * @param ResolverRepositoryInterface $inner  The repository to wrap with caching
     * @param CacheInterface              $cache  PSR-16 cache implementation
     * @param null|int                    $ttl    Cache time-to-live in seconds (null = forever)
     * @param string                      $prefix Cache key prefix to avoid collisions
     */
    public function __construct(
        private ResolverRepositoryInterface $inner,
        private CacheInterface $cache,
        private ?int $ttl = null,
        private string $prefix = 'cascade:resolvers:',
    ) {}

    /**
     * Get a resolver definition by name.
     *
     * Returns cached definition if available, otherwise fetches from inner repository
     * and caches the result.
     *
     * @param  string                    $name The resolver name
     * @throws ResolverNotFoundException If the resolver is not found
     * @return array<string, mixed>      The resolver definition
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
     * Check if a resolver definition exists.
     *
     * Checks cache first, then falls back to inner repository.
     *
     * @param  string $name The resolver name
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
     * Get all resolver definitions.
     *
     * This method bypasses the cache and always fetches from the inner repository,
     * as caching all definitions would be inefficient.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function all(): array
    {
        // Don't cache all() results as it's typically only called once at initialization
        return $this->inner->all();
    }

    /**
     * Get multiple resolver definitions.
     *
     * Fetches from cache where possible, then fetches remaining from inner repository.
     *
     * @param  array<string>                       $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
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
     * Invalidate a cached resolver definition.
     *
     * Removes the cached definition for the specified resolver name.
     * The next get() call will fetch fresh data from the inner repository.
     *
     * @param  string $name The resolver name to invalidate
     * @return bool   True if the cached entry was deleted, false if it didn't exist
     */
    public function forget(string $name): bool
    {
        $cacheKey = $this->getCacheKey($name);

        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear all cached resolver definitions.
     *
     * Removes all cached entries with this repository's prefix.
     * Note: This uses the clear() method which clears the entire cache pool.
     * If you share the cache with other data, consider using a dedicated cache instance.
     *
     * @return bool True if the cache was successfully cleared
     */
    public function flush(): bool
    {
        // Note: PSR-16 doesn't support clearing by prefix, so this clears the entire cache
        // In production, use a dedicated cache pool for resolver definitions
        return $this->cache->clear();
    }

    /**
     * Generate a cache key for a resolver name.
     *
     * @param  string $name The resolver name
     * @return string The cache key
     */
    private function getCacheKey(string $name): string
    {
        return $this->prefix.$name;
    }
}
