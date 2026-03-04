<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Database;

use Cline\Cascade\Database\Models\Resolver as ResolverModel;
use Illuminate\Contracts\Container\Container;

use function assert;
use function class_exists;
use function config;
use function is_array;
use function is_string;

/**
 * Central registry for customizing Cascade database models and tables.
 *
 * Provides a fluent API for overriding default model classes and table names,
 * allowing seamless integration with existing database schemas and custom
 * model implementations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelRegistry
{
    /**
     * Custom model class mappings.
     *
     * @var array<string, class-string>
     */
    private array $models = [];

    /**
     * Custom table name mappings.
     *
     * @var array<string, string>
     */
    private array $tables = [];

    /**
     * Create a new model registry instance.
     */
    public function __construct(
        private readonly Container $container,
    ) {
        $this->loadConfiguration();
    }

    /**
     * Set a custom model class for resolvers.
     *
     * @param class-string $model The custom model class
     */
    public function setResolverModel(string $model): void
    {
        $this->models['resolver'] = $model;
    }

    /**
     * Get the resolver model class.
     *
     * @return class-string The resolver model class
     */
    public function resolverModel(): string
    {
        return $this->models['resolver'] ?? ResolverModel::class;
    }

    /**
     * Set custom table names.
     *
     * @param array<string, string> $map Table name mappings
     */
    public function setTables(array $map): void
    {
        $this->tables = [...$this->tables, ...$map];
    }

    /**
     * Get a table name, falling back to the default.
     *
     * @param string $table The table identifier (e.g., 'resolvers')
     *
     * @return string The actual table name to use
     */
    public function table(string $table): string
    {
        return $this->tables[$table] ?? $table;
    }

    /**
     * Get the primary key type from configuration.
     *
     * @return string The primary key type ('id', 'uuid', or 'ulid')
     */
    public function primaryKeyType(): string
    {
        $type = config('cascade.primary_key_type', 'id');

        return is_string($type) ? $type : 'id';
    }

    /**
     * Check if database storage is enabled.
     *
     * @return bool True if database storage is enabled
     */
    public function isDatabaseEnabled(): bool
    {
        return (bool) config('cascade.use_database', false);
    }

    /**
     * Create a new instance of the resolver model.
     *
     * @param  array<string, mixed> $attributes Model attributes
     * @return ResolverModel        New model instance
     */
    public function newResolverModel(array $attributes = []): ResolverModel
    {
        $class = $this->resolverModel();
        $instance = new $class($attributes);

        assert($instance instanceof ResolverModel);

        return $instance;
    }

    /**
     * Resolve a model instance from the container.
     *
     * @template T
     *
     * @param  class-string<T> $class The model class to resolve
     * @return T               Resolved instance
     */
    public function resolve(string $class): mixed
    {
        return $this->container->make($class);
    }

    /**
     * Load configuration from cascade config file.
     */
    private function loadConfiguration(): void
    {
        // Load custom models
        $models = config('cascade.models', []);

        if (is_array($models) && isset($models['resolver']) && is_string($models['resolver']) && class_exists($models['resolver'])) {
            /** @var class-string $resolverClass */
            $resolverClass = $models['resolver'];
            $this->models['resolver'] = $resolverClass;
        }

        // Load custom tables
        $tables = config('cascade.tables', []);

        if (!is_array($tables) || $tables === []) {
            return;
        }

        /** @var array<string, string> $validTables */
        $validTables = [];

        foreach ($tables as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $validTables[$key] = $value;
        }

        $this->tables = $validTables;
    }
}
