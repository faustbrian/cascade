<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Conductors;

use Cline\Cascade\Source\SourceInterface;
use Cline\Cascade\Cascade;
use Cline\Cascade\Exception\ResolutionFailedException;
use Cline\Cascade\Result;

/**
 * Fluent conductor for named resolver operations with context binding.
 *
 * Provides chainable methods for configuring resolution with context.
 */
final readonly class ResolutionConductor
{
    /**
     * @param Cascade $manager The cascade manager instance
     * @param string $resolverName Named resolver to use
     * @param array<string, mixed> $context Resolution context
     * @param array<callable> $transformers Value transformers to apply
     */
    public function __construct(
        private Cascade $manager,
        private string $resolverName,
        private array $context = [],
        private array $transformers = [],
    ) {}

    /**
     * Bind context for resolution.
     *
     * Accepts a model or array of context values. Models are converted
     * to context arrays using their primary key.
     *
     * @param object|array<string, mixed> $context Context model or array
     */
    public function for(object|array $context): self
    {
        $contextArray = $this->normalizeContext($context);

        return new self(
            manager: $this->manager,
            resolverName: $this->resolverName,
            context: \array_merge($this->context, $contextArray),
            transformers: $this->transformers,
        );
    }

    /**
     * Add a value transformer.
     *
     * Transformers are applied in order after resolution.
     *
     * @param callable(mixed, SourceInterface):mixed $transformer
     */
    public function transform(callable $transformer): self
    {
        return new self(
            manager: $this->manager,
            resolverName: $this->resolverName,
            context: $this->context,
            transformers: [...$this->transformers, $transformer],
        );
    }

    /**
     * Resolve a value with optional default.
     *
     * @param string $key The key to resolve
     * @param mixed $default Default value if not found (supports callables)
     * @return mixed The resolved value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $result = $this->resolve($key);

        if ($result->wasFound()) {
            return $this->applyTransformers($result->getValue(), $result->getSource());
        }

        if (\is_callable($default)) {
            return $default();
        }

        return $default;
    }

    /**
     * Resolve with full result metadata.
     *
     * @param string $key The key to resolve
     */
    public function resolve(string $key): Result
    {
        return $this->manager->resolveUsing($this->resolverName, $key, $this->context);
    }

    /**
     * Resolve or throw if not found.
     *
     * @param string $key The key to resolve
     * @return mixed The resolved value
     *
     * @throws ResolutionFailedException If value not found
     */
    public function getOrFail(string $key): mixed
    {
        $result = $this->resolve($key);

        if (!$result->wasFound()) {
            throw ResolutionFailedException::forKey($key, $result->getAttemptedSources());
        }

        return $this->applyTransformers($result->getValue(), $result->getSource());
    }

    /**
     * Resolve multiple keys.
     *
     * @param array<string> $keys Keys to resolve
     * @return array<string, Result> Results keyed by key
     */
    public function getMany(array $keys): array
    {
        return $this->manager->getManyUsing($this->resolverName, $keys, $this->context);
    }

    /**
     * Apply transformers to a value.
     *
     * @param mixed $value The value to transform
     * @param SourceInterface|null $source The source
     * @return mixed The transformed value
     */
    private function applyTransformers(mixed $value, ?SourceInterface $source): mixed
    {
        foreach ($this->transformers as $transformer) {
            $value = $transformer($value, $source);
        }

        return $value;
    }

    /**
     * Normalize context from model or array.
     *
     * @param object|array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(object|array $context): array
    {
        if (\is_array($context)) {
            return $context;
        }

        // Extract context from model
        $contextArray = [];

        // Use model class name as context key
        if (\method_exists($context, 'getKey')) {
            $class = \class_basename($context);
            $key = \strtolower($class) . '_id';
            $contextArray[$key] = $context->getKey();
        }

        // Support custom context extraction
        if (\method_exists($context, 'toCascadeContext')) {
            $customContext = $context->toCascadeContext();
            if (!\is_array($customContext)) {
                return $contextArray;
            }

            /** @var array<string, mixed> $customContext */
            return \array_merge($contextArray, $customContext);
        }

        return $contextArray;
    }
}
