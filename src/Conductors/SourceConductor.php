<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Conductors;

use Cline\Cascade\Cascade;
use Cline\Cascade\Exception\ResolutionFailedForKeyException;
use Cline\Cascade\Result;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CacheSource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\SourceInterface;
use Psr\SimpleCache\CacheInterface;

use function array_column;
use function array_values;
use function count;
use function is_array;
use function max;
use function md5;
use function serialize;
use function spl_object_id;

/**
 * Fluent conductor for building source chains.
 *
 * Provides a mutable, chainable interface for configuring multi-source resolution
 * pipelines with priorities, caching, and transformations. Sources are queried in
 * priority order (lowest values first) until a value is found.
 *
 * ```php
 * $cascade->from($primarySource, priority: 0)
 *     ->fallbackTo($cacheSource)
 *     ->fallbackTo($defaultSource)
 *     ->transform(fn($v) => json_decode($v))
 *     ->as('config');
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SourceConductor
{
    /**
     * Registered sources with their priority values.
     *
     * Lower priority values are queried first during resolution.
     *
     * @var array<array{source: SourceInterface, priority: int}>
     */
    private array $sources = [];

    /**
     * Value transformers applied after successful resolution.
     *
     * @var array<callable(mixed, SourceInterface): mixed>
     */
    private array $transformers = [];

    /**
     * Optional resolver name for registration.
     */
    private ?string $resolverName = null;

    /**
     * Create a new source conductor instance.
     *
     * @param Cascade $manager Cascade manager for resolver registration
     */
    public function __construct(
        private readonly Cascade $manager,
    ) {}

    /**
     * Create a source conductor with the initial source.
     *
     * Factory method that creates a new conductor instance and adds the first source
     * to the resolution chain.
     *
     * @param Cascade                                     $manager  Cascade manager instance
     * @param array<string, mixed>|SourceInterface|string $source   Source instance, name, or array data
     * @param int                                         $priority Source priority (lower = higher priority, default: 0)
     *
     * @return self New conductor instance with initial source configured
     */
    public static function from(
        Cascade $manager,
        SourceInterface|string|array $source,
        int $priority = 0,
    ): self {
        $conductor = new self($manager);

        return $conductor->addSource($source, $priority);
    }

    /**
     * Add a fallback source to the resolution chain.
     *
     * Automatically assigns a priority value higher than all existing sources,
     * ensuring this source is queried last unless a specific priority is provided.
     *
     * @param array<string, mixed>|SourceInterface|string $source   Source instance, name, or array data
     * @param null|int                                    $priority Explicit priority (auto-incremented if null)
     *
     * @return self Fluent interface for method chaining
     */
    public function fallbackTo(
        SourceInterface|string|array $source,
        ?int $priority = null,
    ): self {
        // Auto-increment priority to ensure fallback behavior
        if ($priority === null) {
            $maxPriority = $this->sources === [] ? 0 : max(array_column($this->sources, 'priority'));
            $priority = $maxPriority + 10;
        }

        return $this->addSource($source, $priority);
    }

    /**
     * Add a source to the resolution chain with explicit priority.
     *
     * Sources with lower priority values are queried first during resolution.
     * Multiple sources can share the same priority value.
     *
     * @param array<string, mixed>|SourceInterface|string $source   Source instance, name, or array data
     * @param int                                         $priority Source priority (lower = higher priority, default: 0)
     *
     * @return self Fluent interface for method chaining
     */
    public function addSource(SourceInterface|string|array $source, int $priority = 0): self
    {
        $sourceInstance = $this->normalizeSource($source);

        $this->sources[] = [
            'source' => $sourceInstance,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * Wrap the most recently added source with caching.
     *
     * Replaces the last added source with a CacheSource decorator that caches
     * resolved values. Useful for expensive sources like database or API calls.
     *
     * @param CacheInterface                                        $cache        PSR-16 compliant cache implementation
     * @param int                                                   $ttl          Cache time-to-live in seconds (default: 300)
     * @param null|(callable(string, array<string, mixed>): string) $keyGenerator Custom cache key generator function
     *
     * @return self Fluent interface for method chaining
     */
    public function cache(
        CacheInterface $cache,
        int $ttl = 300,
        ?callable $keyGenerator = null,
    ): self {
        // No-op if no sources have been added yet
        if ($this->sources === []) {
            return $this;
        }

        $lastIndex = count($this->sources) - 1;
        $last = $this->sources[$lastIndex];

        // Replace last source with cached wrapper
        $this->sources[$lastIndex] = [
            'source' => new CacheSource(
                name: $last['source']->getName().'-cached',
                inner: $last['source'],
                cache: $cache,
                ttl: $ttl,
                keyGenerator: $keyGenerator,
            ),
            'priority' => $last['priority'],
        ];

        return $this;
    }

    /**
     * Add a value transformer to the resolution pipeline.
     *
     * Transformers are applied sequentially after successful resolution from any
     * source. Each transformer receives the value and the source that provided it.
     *
     * @param callable(mixed, SourceInterface): mixed $transformer Transformation function
     *
     * @return self Fluent interface for method chaining
     */
    public function transform(callable $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    /**
     * Register this source chain as a named resolver.
     *
     * Binds the configured source chain to a resolver name, making it available
     * for resolution via `$cascade->using($name)`.
     *
     * @param string $name Unique resolver name for registration
     *
     * @return self Fluent interface for method chaining
     */
    public function as(string $name): self
    {
        $this->resolverName = $name;

        // Register with the cascade manager
        $this->manager->registerSourceChain($name, $this->sources, $this->transformers);

        return $this;
    }

    /**
     * Resolve a value with optional default fallback.
     *
     * Ensures the source chain is registered, then performs resolution using
     * the configured sources and transformers.
     *
     * @param string               $key     Configuration key to resolve
     * @param array<string, mixed> $context Resolution context data for interpolation
     * @param mixed                $default Default value returned if key not found
     *
     * @return mixed The resolved value or default
     */
    public function get(string $key, array $context = [], mixed $default = null): mixed
    {
        $this->ensureRegistered();

        return $this->manager->getUsing($this->getResolverName(), $key, $context, $default);
    }

    /**
     * Resolve with full result metadata.
     *
     * Returns the complete Result object containing the value, source information,
     * and resolution metadata.
     *
     * @param string               $key     Configuration key to resolve
     * @param array<string, mixed> $context Resolution context data for interpolation
     *
     * @return Result Complete resolution result with metadata
     */
    public function resolve(string $key, array $context = []): Result
    {
        $this->ensureRegistered();

        return $this->manager->resolveUsing($this->getResolverName(), $key, $context);
    }

    /**
     * Resolve or throw exception if not found.
     *
     * Guarantees a non-null return value by throwing an exception when resolution
     * fails across all configured sources.
     *
     * @param string               $key     Configuration key to resolve
     * @param array<string, mixed> $context Resolution context data for interpolation
     *
     * @throws ResolutionFailedForKeyException When value cannot be resolved from any source
     * @return mixed                           The resolved value
     */
    public function getOrFail(string $key, array $context = []): mixed
    {
        $this->ensureRegistered();

        $result = $this->resolve($key, $context);

        if (!$result->wasFound()) {
            throw ResolutionFailedForKeyException::withAttemptedSources($key, array_values($result->getAttemptedSources()));
        }

        return $result->getValue();
    }

    /**
     * Resolve multiple keys in a single operation.
     *
     * Performs batch resolution for multiple configuration keys using the same
     * context and source chain.
     *
     * @param array<string>        $keys    Configuration keys to resolve
     * @param array<string, mixed> $context Resolution context data for interpolation
     *
     * @return array<string, Result> Resolution results keyed by configuration key
     */
    public function getMany(array $keys, array $context = []): array
    {
        $this->ensureRegistered();

        return $this->manager->getManyUsing($this->getResolverName(), $keys, $context);
    }

    /**
     * Ensure the source chain is registered with the manager.
     *
     * Automatically registers the source chain with an anonymous name if not
     * already registered via the `as()` method.
     */
    private function ensureRegistered(): void
    {
        if ($this->resolverName !== null) {
            return;
        }

        // Auto-generate anonymous name and register
        $this->resolverName = 'anonymous-'.spl_object_id($this);
        $this->manager->registerSourceChain($this->resolverName, $this->sources, $this->transformers);
    }

    /**
     * Get the resolver name for this source chain.
     *
     * Returns the explicit name if set via `as()`, or generates an anonymous
     * name based on the object ID.
     *
     * @return string Resolver name
     */
    private function getResolverName(): string
    {
        return $this->resolverName ?? 'anonymous-'.spl_object_id($this);
    }

    /**
     * Normalize source from various input types.
     *
     * Converts strings, arrays, and objects into SourceInterface implementations.
     * Strings become context lookups, arrays become ArraySource instances.
     *
     * @param array<string, mixed>|SourceInterface|string $source Source specification
     *
     * @return SourceInterface Normalized source instance
     */
    private function normalizeSource(SourceInterface|string|array $source): SourceInterface
    {
        if ($source instanceof SourceInterface) {
            return $source;
        }

        if (is_array($source)) {
            // Create array source from key-value pairs
            $name = 'array-'.md5(serialize($source));

            return new ArraySource($name, $source);
        }

        // String name - create a callback source that performs context lookup
        return new CallbackSource(
            name: $source,
            resolver: fn (string $key, array $ctx): mixed => $ctx[$key] ?? null,
        );
    }
}
