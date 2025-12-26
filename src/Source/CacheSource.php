<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

use Psr\SimpleCache\CacheInterface;

use function md5;
use function serialize;

/**
 * PSR-16 cache decorator for any source.
 *
 * Wraps another source and caches resolved values to improve performance by avoiding
 * expensive operations on cache hits. Uses the decorator pattern to add caching behavior
 * transparently without modifying the wrapped source. Supports custom cache key generation
 * for fine-grained cache control.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CacheSource implements SourceInterface
{
    /**
     * Create a new cache-enabled source wrapper.
     *
     * @param string                                              $name         Unique identifier for this source. Used for
     *                                                                          debugging and metadata reporting.
     * @param SourceInterface                                     $inner        The source to wrap with caching. All resolution
     *                                                                          requests pass through to this source on cache miss.
     * @param CacheInterface                                      $cache        PSR-16 cache implementation for storing and
     *                                                                          retrieving cached values.
     * @param int                                                 $ttl          Time-to-live in seconds for cached values.
     *                                                                          Default: 300 seconds (5 minutes).
     * @param null|callable(string, array<string, mixed>): string $keyGenerator Optional custom cache key generator. If not
     *                                                                          provided, uses a default key combining the
     *                                                                          key and serialized context.
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
     * Delegates support checking to the wrapped source. The cache decorator does not
     * modify support behavior, only adds caching to resolution.
     *
     * @param  string               $key     The key being resolved
     * @param  array<string, mixed> $context Additional context for resolution
     * @return bool                 True if the wrapped source supports the key/context, false otherwise
     */
    public function supports(string $key, array $context): bool
    {
        return $this->inner->supports($key, $context);
    }

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Implements a read-through cache strategy: checks cache first for a hit, and on cache
     * miss queries the wrapped source. Non-null values from the wrapped source are cached
     * for the configured TTL. Null values are not cached to allow retry on transient failures.
     *
     * @param  string               $key     The key to resolve
     * @param  array<string, mixed> $context Additional context for resolution
     * @return mixed                The cached value if available, otherwise the value from the wrapped source
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
     * Includes cache-specific information such as TTL, the wrapped source name,
     * and whether a custom key generator is configured.
     *
     * @return array<string, mixed> Metadata including name, type, TTL, wrapped source, and key generator status
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
     * If a custom key generator was provided at construction, uses it to generate the cache key.
     * Otherwise, uses a default strategy combining the cascade namespace, the key, and an MD5
     * hash of the serialized context for uniqueness.
     *
     * @param  string               $key     The resolution key
     * @param  array<string, mixed> $context The resolution context
     * @return string               The cache key for storing/retrieving the cached value
     */
    private function generateCacheKey(string $key, array $context): string
    {
        if ($this->keyGenerator !== null) {
            return ($this->keyGenerator)($key, $context);
        }

        return 'cascade:'.md5($key.serialize($context));
    }
}
