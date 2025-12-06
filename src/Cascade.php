<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade;

use Cline\Cascade\Conductors\ResolutionConductor;
use Cline\Cascade\Conductors\SourceConductor;
use Cline\Cascade\Event\ResolutionFailed;
use Cline\Cascade\Event\SourceQueried;
use Cline\Cascade\Event\ValueResolved;
use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Source\SourceInterface;

/**
 * Main facade for cascade resolution operations.
 *
 * Manages multiple named resolvers and provides factory methods
 * for creating conductors that build fluent resolution chains.
 */
final class Cascade
{
    /** @var array<string, Resolver> */
    private array $resolvers = [];

    /** @var array<callable> */
    private array $sourceQueriedListeners = [];

    /** @var array<callable> */
    private array $resolvedListeners = [];

    /** @var array<callable> */
    private array $failedListeners = [];

    /**
     * Create a source conductor for fluent source chaining.
     *
     * @param SourceInterface|string|array<string, mixed> $source Initial source
     * @param int $priority Priority for the source (lower values queried first)
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
     * @param string $name Name of the resolver to use
     *
     * @throws ResolverNotFoundException If resolver doesn't exist
     */
    public function using(string $name): ResolutionConductor
    {
        if (!$this->hasResolver($name)) {
            $available = \array_keys($this->resolvers);

            if ($available !== []) {
                throw ResolverNotFoundException::withSuggestions($name, $available);
            }

            throw ResolverNotFoundException::noResolversRegistered();
        }

        return new ResolutionConductor($this, $name);
    }

    /**
     * Define a named resolver.
     *
     * Returns the resolver for direct configuration if needed.
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
     * Used internally by conductors.
     *
     * @param string $name Resolver name
     * @param array<array{source: SourceInterface, priority: int}> $sources Source configurations
     * @param array<callable> $transformers Value transformers
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
     */
    public function hasResolver(string $name): bool
    {
        return isset($this->resolvers[$name]);
    }

    /**
     * Get a named resolver.
     *
     * @throws ResolverNotFoundException
     */
    public function getResolver(string $name): Resolver
    {
        if (!isset($this->resolvers[$name])) {
            throw ResolverNotFoundException::forName($name);
        }

        return $this->resolvers[$name];
    }

    /**
     * Resolve using a named resolver.
     *
     * Used internally by conductors.
     *
     * @param string $resolverName Name of the resolver
     * @param string $key Key to resolve
     * @param array<string, mixed> $context Resolution context
     */
    public function resolveUsing(string $resolverName, string $key, array $context = []): Result
    {
        $resolver = $this->getResolver($resolverName);

        $startTime = \microtime(true);
        $result = $resolver->resolve($key, $context);
        $duration = (\microtime(true) - $startTime) * 1000;

        // Emit events
        $this->emitSourceQueriedEvents($key, $context, $result);

        if ($result->wasFound()) {
            $this->emitValueResolved($key, $result, $duration, $context);
        } else {
            $this->emitResolutionFailed($key, $result, $context);
        }

        return $result;
    }

    /**
     * Get value using a named resolver.
     *
     * Used internally by conductors.
     *
     * @param string $resolverName Name of the resolver
     * @param string $key Key to resolve
     * @param array<string, mixed> $context Resolution context
     * @param mixed $default Default value if not found
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
     * Used internally by conductors.
     *
     * @param string $resolverName Name of the resolver
     * @param array<string> $keys Keys to resolve
     * @param array<string, mixed> $context Resolution context
     * @return array<string, Result>
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
     * Register source queried listener.
     */
    public function onSourceQueried(callable $listener): self
    {
        $this->sourceQueriedListeners[] = $listener;

        return $this;
    }

    /**
     * Register resolved listener.
     */
    public function onResolved(callable $listener): self
    {
        $this->resolvedListeners[] = $listener;

        return $this;
    }

    /**
     * Register failed listener.
     */
    public function onFailed(callable $listener): self
    {
        $this->failedListeners[] = $listener;

        return $this;
    }

    /**
     * Emit source queried events.
     *
     * @param array<string, mixed> $context
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
     * Emit value resolved event.
     *
     * @param array<string, mixed> $context
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
     * Emit resolution failed event.
     *
     * @param array<string, mixed> $context
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
