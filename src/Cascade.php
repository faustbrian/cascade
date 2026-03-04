<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade;

use Cline\Cascade\Conductors\ResolutionConductor;
use Cline\Cascade\Conductors\SourceConductor;
use Cline\Cascade\Event\ResolutionFailed;
use Cline\Cascade\Event\SourceQueried;
use Cline\Cascade\Event\ValueResolved;
use Cline\Cascade\Exception\NoResolversRegisteredException;
use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Exception\ResolverNotFoundWithSuggestionsException;
use Cline\Cascade\Source\SourceInterface;

use function array_keys;
use function microtime;

/**
 * Main facade for cascade resolution operations.
 *
 * Provides a fluent interface for configuring and executing multi-source value resolution
 * with support for priorities, transformers, and event listeners. This is the primary
 * entry point for the Cascade library.
 *
 * ```php
 * $cascade = new Cascade();
 *
 * // Define a resolver with multiple sources
 * $cascade->from($databaseSource)
 *     ->fallbackTo($cacheSource)
 *     ->as('config');
 *
 * // Use the resolver
 * $value = $cascade->using('config')->get('app.name');
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Cascade
{
    /**
     * Registry of named resolvers keyed by resolver name.
     *
     * @var array<string, Resolver>
     */
    private array $resolvers = [];

    /**
     * Event listeners invoked when a source is queried during resolution.
     *
     * @var array<callable(SourceQueried): void>
     */
    private array $sourceQueriedListeners = [];

    /**
     * Event listeners invoked when a value is successfully resolved.
     *
     * @var array<callable(ValueResolved): void>
     */
    private array $resolvedListeners = [];

    /**
     * Event listeners invoked when resolution fails across all sources.
     *
     * @var array<callable(ResolutionFailed): void>
     */
    private array $failedListeners = [];

    /**
     * Create a source conductor for fluent source chaining.
     *
     * This factory method initiates a fluent chain for configuring resolution sources.
     * Lower priority values are queried first during resolution.
     *
     * @param array<string, mixed>|SourceInterface|string $source   Initial source instance, name, or array data
     * @param int                                         $priority Source priority (lower values = higher priority, default: 0)
     *
     * @return SourceConductor Fluent conductor for building source chains
     */
    public function from(
        SourceInterface|string|array $source,
        int $priority = 0,
    ): SourceConductor {
        return SourceConductor::from($this, $source, $priority);
    }

    /**
     * Create a resolution conductor for a named resolver.
     *
     * Returns a fluent conductor bound to the specified resolver, allowing
     * method chaining for context binding and value transformation before resolution.
     *
     * @param string $name Registered resolver name to use for resolution
     *
     * @throws NoResolversRegisteredException           When no resolvers are registered at all
     * @throws ResolverNotFoundWithSuggestionsException When resolver doesn't exist but others are registered
     * @return ResolutionConductor                      Fluent conductor for executing resolution operations
     */
    public function using(string $name): ResolutionConductor
    {
        if (!$this->hasResolver($name)) {
            $available = array_keys($this->resolvers);

            if ($available !== []) {
                throw ResolverNotFoundWithSuggestionsException::forName($name, $available);
            }

            throw NoResolversRegisteredException::create();
        }

        return new ResolutionConductor($this, $name);
    }

    /**
     * Define a named resolver for direct configuration.
     *
     * Creates and registers a new resolver instance that can be configured
     * programmatically by calling methods directly on the returned instance.
     *
     * @param string $name Unique resolver name
     *
     * @return Resolver The created resolver instance for method chaining
     */
    public function defineResolver(string $name): Resolver
    {
        $resolver = new Resolver($name);
        $this->resolvers[$name] = $resolver;

        return $resolver;
    }

    /**
     * Register a source chain as a named resolver.
     *
     * Used internally by conductors to convert fluent source chains into
     * registered resolvers. Creates a new resolver with the provided sources
     * and transformers configured.
     *
     * @internal
     * @param string                                               $name         Unique resolver name
     * @param array<array{source: SourceInterface, priority: int}> $sources      Source configurations with priorities
     * @param array<callable(mixed, SourceInterface): mixed>       $transformers Value transformation callables
     */
    public function registerSourceChain(string $name, array $sources, array $transformers = []): void
    {
        $resolver = new Resolver($name);

        foreach ($sources as $config) {
            $resolver->source(
                name: $config['source']->getName(),
                source: $config['source'],
                priority: $config['priority'],
            );
        }

        foreach ($transformers as $transformer) {
            $resolver->transform($transformer);
        }

        $this->resolvers[$name] = $resolver;
    }

    /**
     * Check if a resolver exists.
     *
     * @param string $name Resolver name to check
     *
     * @return bool True if resolver is registered, false otherwise
     */
    public function hasResolver(string $name): bool
    {
        return isset($this->resolvers[$name]);
    }

    /**
     * Get a named resolver instance.
     *
     * @param string $name Registered resolver name
     *
     * @throws ResolverNotFoundException When resolver is not registered
     * @return Resolver                  The resolver instance
     */
    public function getResolver(string $name): Resolver
    {
        if (!isset($this->resolvers[$name])) {
            throw ResolverNotFoundException::forName($name);
        }

        return $this->resolvers[$name];
    }

    /**
     * Resolve using a named resolver with full result metadata.
     *
     * Performs resolution, tracks timing, and emits appropriate events based
     * on success or failure. Used internally by conductors for resolution operations.
     *
     * @internal
     * @param  string               $resolverName Registered resolver name to use
     * @param  string               $key          Configuration key to resolve
     * @param  array<string, mixed> $context      Resolution context data for interpolation
     * @return Result               Complete resolution result with metadata
     */
    public function resolveUsing(string $resolverName, string $key, array $context = []): Result
    {
        $resolver = $this->getResolver($resolverName);

        $startTime = microtime(true);
        $result = $resolver->resolve($key, $context);
        $duration = (microtime(true) - $startTime) * 1_000;

        // Emit events for observability
        $this->emitSourceQueriedEvents($key, $context, $result);

        if ($result->wasFound()) {
            $this->emitValueResolved($key, $result, $duration, $context);
        } else {
            $this->emitResolutionFailed($key, $result, $context);
        }

        return $result;
    }

    /**
     * Get value using a named resolver with optional default.
     *
     * Simplified resolution method that returns the value directly rather
     * than the full result metadata. Used internally by conductors.
     *
     * @internal
     * @param  string               $resolverName Registered resolver name to use
     * @param  string               $key          Configuration key to resolve
     * @param  array<string, mixed> $context      Resolution context data for interpolation
     * @param  mixed                $default      Default value returned if key not found
     * @return mixed                The resolved value or default
     */
    public function getUsing(
        string $resolverName,
        string $key,
        array $context = [],
        mixed $default = null,
    ): mixed {
        $resolver = $this->getResolver($resolverName);

        return $resolver->get($key, $context, $default);
    }

    /**
     * Resolve multiple keys using a named resolver.
     *
     * Performs batch resolution for multiple keys using the same resolver and context.
     * Used internally by conductors for efficient multi-key resolution.
     *
     * @internal
     * @param  string                $resolverName Registered resolver name to use
     * @param  array<string>         $keys         Configuration keys to resolve
     * @param  array<string, mixed>  $context      Resolution context data for interpolation
     * @return array<string, Result> Resolution results keyed by configuration key
     */
    public function getManyUsing(string $resolverName, array $keys, array $context = []): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->resolveUsing($resolverName, $key, $context);
        }

        return $results;
    }

    /**
     * Register an event listener for source query events.
     *
     * Listeners are invoked each time a source is queried during resolution,
     * providing visibility into the resolution process for debugging and monitoring.
     *
     * @param callable(SourceQueried): void $listener Event listener callback
     *
     * @return self Fluent interface for method chaining
     */
    public function onSourceQueried(callable $listener): self
    {
        $this->sourceQueriedListeners[] = $listener;

        return $this;
    }

    /**
     * Register an event listener for successful resolution events.
     *
     * Listeners are invoked when a value is successfully resolved from any source,
     * receiving the resolved value, source name, and timing information.
     *
     * @param callable(ValueResolved): void $listener Event listener callback
     *
     * @return self Fluent interface for method chaining
     */
    public function onResolved(callable $listener): self
    {
        $this->resolvedListeners[] = $listener;

        return $this;
    }

    /**
     * Register an event listener for resolution failure events.
     *
     * Listeners are invoked when resolution fails across all configured sources,
     * receiving the attempted sources list for debugging purposes.
     *
     * @param callable(ResolutionFailed): void $listener Event listener callback
     *
     * @return self Fluent interface for method chaining
     */
    public function onFailed(callable $listener): self
    {
        $this->failedListeners[] = $listener;

        return $this;
    }

    /**
     * Emit source queried events for all attempted sources.
     *
     * Creates and dispatches a SourceQueried event for each source that was
     * attempted during resolution, allowing listeners to track the resolution flow.
     *
     * @param string               $key     Configuration key being resolved
     * @param array<string, mixed> $context Resolution context data
     * @param Result               $result  Resolution result containing attempted sources
     */
    private function emitSourceQueriedEvents(string $key, array $context, Result $result): void
    {
        foreach ($result->getAttemptedSources() as $sourceName) {
            $event = new SourceQueried(
                sourceName: $sourceName,
                key: $key,
                context: $context,
            );

            foreach ($this->sourceQueriedListeners as $listener) {
                $listener($event);
            }
        }
    }

    /**
     * Emit value resolved event after successful resolution.
     *
     * Creates and dispatches a ValueResolved event containing the resolved value,
     * source name, resolution duration, and context information.
     *
     * @param string               $key      Configuration key that was resolved
     * @param Result               $result   Resolution result containing the value
     * @param float                $duration Resolution duration in milliseconds
     * @param array<string, mixed> $context  Resolution context data
     */
    private function emitValueResolved(
        string $key,
        Result $result,
        float $duration,
        array $context,
    ): void {
        $event = new ValueResolved(
            key: $key,
            value: $result->getValue(),
            sourceName: $result->getSourceName() ?? 'unknown',
            durationMs: $duration,
            context: $context,
        );

        foreach ($this->resolvedListeners as $listener) {
            $listener($event);
        }
    }

    /**
     * Emit resolution failed event after all sources fail.
     *
     * Creates and dispatches a ResolutionFailed event containing the failed key,
     * all attempted source names, and the resolution context.
     *
     * @param string               $key     Configuration key that failed to resolve
     * @param Result               $result  Resolution result containing attempted sources
     * @param array<string, mixed> $context Resolution context data
     */
    private function emitResolutionFailed(string $key, Result $result, array $context): void
    {
        $event = new ResolutionFailed(
            key: $key,
            attemptedSources: $result->getAttemptedSources(),
            context: $context,
        );

        foreach ($this->failedListeners as $listener) {
            $listener($event);
        }
    }
}
