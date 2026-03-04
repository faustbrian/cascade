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
use Cline\Cascade\Source\SourceInterface;

use function array_merge;
use function array_values;
use function class_basename;
use function is_array;
use function is_callable;
use function mb_strtolower;
use function method_exists;

/**
 * Fluent conductor for named resolver operations with context binding.
 *
 * Provides an immutable, chainable interface for configuring resolution operations
 * with context data and value transformations. Each method returns a new instance,
 * allowing safe composition of resolution pipelines.
 *
 * ```php
 * $value = $cascade->using('config')
 *     ->for(['tenant_id' => 123])
 *     ->transform(fn($v) => strtoupper($v))
 *     ->get('app.name');
 * ```
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ResolutionConductor
{
    /**
     * Create a new resolution conductor instance.
     *
     * @param Cascade                                        $manager      Cascade manager instance for resolver access
     * @param string                                         $resolverName Named resolver to use for resolution
     * @param array<string, mixed>                           $context      Resolution context data for interpolation
     * @param array<callable(mixed, SourceInterface): mixed> $transformers Value transformers applied after resolution
     */
    public function __construct(
        private Cascade $manager,
        private string $resolverName,
        private array $context = [],
        private array $transformers = [],
    ) {}

    /**
     * Bind context data for resolution.
     *
     * Merges additional context data into the resolution pipeline. Supports both
     * arrays and model objects. Models are automatically converted to context arrays
     * using `getKey()` or a custom `toCascadeContext()` method if available.
     *
     * @param array<string, mixed>|object $context Context data array or model instance
     *
     * @return self New immutable instance with merged context
     */
    public function for(object|array $context): self
    {
        $contextArray = $this->normalizeContext($context);

        return new self(
            manager: $this->manager,
            resolverName: $this->resolverName,
            context: array_merge($this->context, $contextArray),
            transformers: $this->transformers,
        );
    }

    /**
     * Add a value transformer to the resolution pipeline.
     *
     * Transformers are applied sequentially in the order they are added, after
     * successful resolution. Each transformer receives the current value and the
     * source that provided it.
     *
     * @param callable(mixed, SourceInterface): mixed $transformer Transformation function
     *
     * @return self New immutable instance with appended transformer
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
     * Resolve a value with optional default fallback.
     *
     * Returns the resolved and transformed value, or the default if resolution fails.
     * Callable defaults are evaluated lazily only when needed.
     *
     * @param string $key     Configuration key to resolve
     * @param mixed  $default Default value or callable returning default value
     *
     * @return mixed The resolved and transformed value, or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $result = $this->resolve($key);

        if ($result->wasFound()) {
            return $this->applyTransformers($result->getValue(), $result->getSource());
        }

        if (is_callable($default)) {
            return $default();
        }

        return $default;
    }

    /**
     * Resolve with full result metadata.
     *
     * Returns the complete Result object containing the value, source information,
     * and resolution metadata. Transformers are NOT applied to the result value.
     *
     * @param string $key Configuration key to resolve
     *
     * @return Result Complete resolution result with metadata
     */
    public function resolve(string $key): Result
    {
        return $this->manager->resolveUsing($this->resolverName, $key, $this->context);
    }

    /**
     * Resolve or throw exception if not found.
     *
     * Guarantees a non-null return value by throwing an exception when resolution
     * fails. Useful for required configuration values that must exist.
     *
     * @param string $key Configuration key to resolve
     *
     * @throws ResolutionFailedForKeyException When value cannot be resolved from any source
     * @return mixed                           The resolved and transformed value
     */
    public function getOrFail(string $key): mixed
    {
        $result = $this->resolve($key);

        if (!$result->wasFound()) {
            throw ResolutionFailedForKeyException::withAttemptedSources($key, array_values($result->getAttemptedSources()));
        }

        return $this->applyTransformers($result->getValue(), $result->getSource());
    }

    /**
     * Resolve multiple keys in a single operation.
     *
     * Performs batch resolution for multiple configuration keys using the same
     * context. Returns Result objects without applying transformers.
     *
     * @param array<string> $keys Configuration keys to resolve
     *
     * @return array<string, Result> Resolution results keyed by configuration key
     */
    public function getMany(array $keys): array
    {
        return $this->manager->getManyUsing($this->resolverName, $keys, $this->context);
    }

    /**
     * Apply all configured transformers to a resolved value.
     *
     * Transformers are executed sequentially in the order they were added,
     * with each transformer receiving the output of the previous one.
     *
     * @param mixed                $value  The value to transform
     * @param null|SourceInterface $source The source that provided the value
     *
     * @return mixed The fully transformed value
     */
    private function applyTransformers(mixed $value, ?SourceInterface $source): mixed
    {
        if (!$source instanceof SourceInterface) {
            return $value;
        }

        foreach ($this->transformers as $transformer) {
            $value = $transformer($value, $source);
        }

        return $value;
    }

    /**
     * Normalize context from model object or array.
     *
     * Converts model objects to context arrays by extracting relevant data.
     * Supports Eloquent models (via `getKey()`) and custom context extraction
     * via the `toCascadeContext()` method.
     *
     * @param array<string, mixed>|object $context Model instance or context array
     *
     * @return array<string, mixed> Normalized context data
     */
    private function normalizeContext(object|array $context): array
    {
        if (is_array($context)) {
            return $context;
        }

        // Extract context from model object
        $contextArray = [];

        // Extract primary key from Eloquent-like models
        if (method_exists($context, 'getKey')) {
            $class = class_basename($context);
            $key = mb_strtolower($class).'_id';
            $contextArray[$key] = $context->getKey();
        }

        // Allow models to provide custom context via toCascadeContext()
        if (method_exists($context, 'toCascadeContext')) {
            $customContext = $context->toCascadeContext();

            if (!is_array($customContext)) {
                return $contextArray;
            }

            /** @var array<string, mixed> $customContext */
            return array_merge($contextArray, $customContext);
        }

        return $contextArray;
    }
}
