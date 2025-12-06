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
use function count;
use function is_array;
use function max;
use function md5;
use function serialize;
use function spl_object_id;

/**
 * Fluent conductor for building source chains.
 *
 * Provides chainable methods for adding sources with priorities
 * and configuring resolution behavior.
 * @author Brian Faust <brian@cline.sh>
 */
final class SourceConductor
{
    /** @var array<array{source: SourceInterface, priority: int}> */
    private array $sources = [];

    /** @var array<callable> */
    private array $transformers = [];

    private ?string $resolverName = null;

    /**
     * @param Cascade $manager The cascade manager instance
     */
    public function __construct(
        private readonly Cascade $manager,
    ) {}

    /**
     * Add the initial source.
     *
     * @param array<string, mixed>|SourceInterface|string $source   Source instance, name, or array
     * @param int                                         $priority Priority (lower values queried first)
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
     * Add a fallback source.
     *
     * @param array<string, mixed>|SourceInterface|string $source   Source instance, name, or array
     * @param int                                         $priority Priority (defaults to next priority)
     */
    public function fallbackTo(
        SourceInterface|string|array $source,
        ?int $priority = null,
    ): self {
        // Auto-increment priority if not specified
        if ($priority === null) {
            $maxPriority = $this->sources === [] ? 0 : max(array_column($this->sources, 'priority'));
            $priority = $maxPriority + 10;
        }

        return $this->addSource($source, $priority);
    }

    /**
     * Add a source with priority.
     *
     * @param array<string, mixed>|SourceInterface|string $source
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
     * Wrap the current source chain in a cache.
     *
     * @param CacheInterface                                        $cache        PSR-16 cache instance
     * @param int                                                   $ttl          Time to live in seconds
     * @param null|(callable(string, array<string, mixed>): string) $keyGenerator Custom key generator
     */
    public function cache(
        CacheInterface $cache,
        int $ttl = 300,
        ?callable $keyGenerator = null,
    ): self {
        // Wrap last added source in cache
        if ($this->sources === []) {
            return $this;
        }

        $lastIndex = count($this->sources) - 1;
        $last = $this->sources[$lastIndex];

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
     * Add a value transformer.
     *
     * @param callable(mixed, SourceInterface): mixed $transformer
     */
    public function transform(callable $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    /**
     * Bind this source chain to a named resolver.
     *
     * @param string $name Resolver name
     */
    public function as(string $name): self
    {
        $this->resolverName = $name;

        // Register with manager
        $this->manager->registerSourceChain($name, $this->sources, $this->transformers);

        return $this;
    }

    /**
     * Resolve a value with optional default.
     *
     * @param string               $key     The key to resolve
     * @param array<string, mixed> $context Resolution context
     * @param mixed                $default Default value if not found
     */
    public function get(string $key, array $context = [], mixed $default = null): mixed
    {
        $this->ensureRegistered();

        return $this->manager->getUsing($this->getResolverName(), $key, $context, $default);
    }

    /**
     * Resolve with full result metadata.
     *
     * @param string               $key     The key to resolve
     * @param array<string, mixed> $context Resolution context
     */
    public function resolve(string $key, array $context = []): Result
    {
        $this->ensureRegistered();

        return $this->manager->resolveUsing($this->getResolverName(), $key, $context);
    }

    /**
     * Resolve or throw if not found.
     *
     * @param string               $key     The key to resolve
     * @param array<string, mixed> $context Resolution context
     *
     * @throws ResolutionFailedException
     */
    public function getOrFail(string $key, array $context = []): mixed
    {
        $this->ensureRegistered();

        $result = $this->resolve($key, $context);

        if (!$result->wasFound()) {
            throw ResolutionFailedForKeyException::withAttemptedSources($key, $result->getAttemptedSources());
        }

        return $result->getValue();
    }

    /**
     * Resolve multiple keys.
     *
     * @param  array<string>         $keys    Keys to resolve
     * @param  array<string, mixed>  $context Resolution context
     * @return array<string, Result>
     */
    public function getMany(array $keys, array $context = []): array
    {
        $this->ensureRegistered();

        return $this->manager->getManyUsing($this->getResolverName(), $keys, $context);
    }

    /**
     * Ensure the source chain is registered.
     */
    private function ensureRegistered(): void
    {
        if ($this->resolverName !== null) {
            return;
        }

        // Auto-generate name and register
        $this->resolverName = 'anonymous-'.spl_object_id($this);
        $this->manager->registerSourceChain($this->resolverName, $this->sources, $this->transformers);
    }

    /**
     * Get the resolver name.
     */
    private function getResolverName(): string
    {
        return $this->resolverName ?? 'anonymous-'.spl_object_id($this);
    }

    /**
     * Normalize source from various input types.
     *
     * @param array<string, mixed>|SourceInterface|string $source
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

        // String name - create a callback source that looks up by name
        return new CallbackSource(
            name: $source,
            resolver: fn (string $key, array $ctx): mixed => $ctx[$key] ?? null,
        );
    }
}
