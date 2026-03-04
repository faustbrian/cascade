<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade;

use Cline\Cascade\Exception\ResolutionFailedForKeyException;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\SourceInterface;

use function array_map;
use function is_callable;
use function usort;

/**
 * Resolver manages a named collection of sources and resolution logic.
 *
 * Resolvers execute the cascade resolution algorithm by trying each source in priority
 * order until a value is found or all sources fail. Sources are sorted by priority with
 * lower values being queried first. Supports value transformation via registered transformers
 * that are applied after successful resolution.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Resolver
{
    /**
     * Internal array of sources with their priority values.
     *
     * @var array<int, array{source: SourceInterface, priority: int}>
     */
    private array $sources = [];

    /**
     * List of transformer callables to apply to resolved values.
     *
     * @var array<callable>
     */
    private array $transformers = [];

    /**
     * Tracks whether sources have been sorted by priority.
     */
    private bool $sourcesSorted = false;

    /**
     * Create a new resolver with a unique name.
     *
     * @param string $name Unique identifier for this resolver. Used for debugging and logging
     *                     to distinguish between different resolvers in the cascade chain.
     */
    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Add a source with optional name and priority.
     *
     * Sources are queried in priority order during resolution, with lower priority
     * values being attempted first (e.g., priority 1 before priority 10). This allows
     * for fine-grained control over the cascade order.
     *
     * @param  string          $name     Source name for debugging and metadata
     * @param  SourceInterface $source   The source instance to add
     * @param  int             $priority Query priority (lower values = higher priority, default: 0)
     * @return self            Fluent interface for method chaining
     */
    public function source(
        string $name,
        SourceInterface $source,
        int $priority = 0,
    ): self {
        $this->sources[] = [
            'source' => $source,
            'priority' => $priority,
        ];

        $this->sourcesSorted = false;

        return $this;
    }

    /**
     * Add a callback source.
     *
     * Convenience method for creating and adding a CallbackSource in a single operation.
     * Useful for defining custom resolution logic inline without creating a separate source class.
     *
     * @param  string        $name     Source name for debugging and metadata
     * @param  callable      $resolver Closure that resolves values: `fn(string $key, array $context): mixed`
     * @param  null|callable $supports Optional closure to check if source supports a key/context: `fn(string $key, array $context): bool`
     * @param  int           $priority Query priority (lower values = higher priority, default: 0)
     * @return self          Fluent interface for method chaining
     */
    public function fromCallback(
        string $name,
        callable $resolver,
        ?callable $supports = null,
        int $priority = 0,
    ): self {
        return $this->source(
            name: $name,
            source: new CallbackSource(
                name: $name,
                resolver: $resolver,
                supports: $supports,
            ),
            priority: $priority,
        );
    }

    /**
     * Add static values as a source.
     *
     * Convenience method for creating and adding an ArraySource in a single operation.
     * Useful for providing default values or configuration overrides.
     *
     * @param  string               $name     Source name for debugging and metadata
     * @param  array<string, mixed> $values   Key-value pairs to resolve from
     * @param  int                  $priority Query priority (lower values = higher priority, default: 0)
     * @return self                 Fluent interface for method chaining
     */
    public function fromArray(
        string $name,
        array $values,
        int $priority = 0,
    ): self {
        return $this->source(
            name: $name,
            source: new ArraySource(
                name: $name,
                values: $values,
            ),
            priority: $priority,
        );
    }

    /**
     * Add a transformer.
     *
     * Transformers are applied to resolved values in the order they are registered.
     * Each transformer receives the current value and the source that provided it,
     * allowing for source-specific transformations.
     *
     * @param  callable $transformer Closure to transform values: `fn(mixed $value, SourceInterface $source): mixed`
     * @return self     Fluent interface for method chaining
     */
    public function transform(callable $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    /**
     * Resolve a value with default fallback.
     *
     * Attempts to resolve the key through the source cascade. If no source provides a value,
     * returns the default. If the default is callable, it will be invoked and its result returned.
     *
     * @param  string               $key     The key to resolve
     * @param  array<string, mixed> $context Additional context for conditional resolution
     * @param  mixed                $default Default value or callable to invoke if resolution fails
     * @return mixed                The resolved value, default value, or result of calling the default callable
     */
    public function get(
        string $key,
        array $context = [],
        mixed $default = null,
    ): mixed {
        $result = $this->resolve($key, $context);

        if ($result->wasFound()) {
            return $result->getValue();
        }

        if (is_callable($default)) {
            return $default();
        }

        return $default;
    }

    /**
     * Resolve with full result metadata.
     *
     * Performs the cascade resolution algorithm by trying each source in priority order.
     * Returns a Result object containing the resolved value, source information, list of
     * attempted sources, and metadata. This method provides complete visibility into the
     * resolution process for debugging and logging.
     *
     * @param  string               $key     The key to resolve
     * @param  array<string, mixed> $context Additional context for conditional resolution
     * @return Result               Complete resolution result with value, source, attempts, and metadata
     */
    public function resolve(string $key, array $context = []): Result
    {
        $this->ensureSourcesSorted();

        $attempted = [];

        foreach ($this->sources as $entry) {
            $source = $entry['source'];

            if (!$source->supports($key, $context)) {
                continue;
            }

            $attempted[] = $source->getName();

            $value = $source->get($key, $context);

            if ($value !== null) {
                // Apply transformers
                foreach ($this->transformers as $transformer) {
                    $value = $transformer($value, $source);
                }

                return Result::found(
                    value: $value,
                    source: $source,
                    attempted: $attempted,
                    metadata: $source->getMetadata(),
                );
            }
        }

        return Result::notFound($attempted);
    }

    /**
     * Resolve or throw if not found.
     *
     * Attempts to resolve the key through the source cascade. If no source provides a value,
     * throws an exception with details about which sources were attempted.
     *
     * @param  string                          $key     The key to resolve
     * @param  array<string, mixed>            $context Additional context for conditional resolution
     * @throws ResolutionFailedForKeyException If no source provides a value for the key
     * @return mixed                           The resolved value
     */
    public function getOrFail(string $key, array $context = []): mixed
    {
        $result = $this->resolve($key, $context);

        if (!$result->wasFound()) {
            throw ResolutionFailedForKeyException::withAttemptedSources($key, $result->getAttemptedSources());
        }

        return $result->getValue();
    }

    /**
     * Get the name of this resolver.
     *
     * @return string The unique identifier for this resolver
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get ordered sources.
     *
     * Returns sources sorted by priority in the order they will be queried during resolution.
     * Useful for debugging and understanding the cascade order.
     *
     * @return array<SourceInterface> Array of sources ordered by priority (lowest first)
     */
    public function getSources(): array
    {
        $this->ensureSourcesSorted();

        return array_map(
            static fn (array $entry): SourceInterface => $entry['source'],
            $this->sources,
        );
    }

    /**
     * Ensure sources are sorted by priority.
     *
     * Sorts sources by priority on first access, then caches the result using the
     * sourcesSorted flag to avoid redundant sorting. Sources must be sorted before
     * resolution to ensure the cascade algorithm queries them in the correct order.
     */
    private function ensureSourcesSorted(): void
    {
        if ($this->sourcesSorted) {
            return;
        }

        usort(
            $this->sources,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority'],
        );

        $this->sourcesSorted = true;
    }
}
