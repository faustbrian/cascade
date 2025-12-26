<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Database\ModelRegistry;
use Cline\Cascade\Database\Models\Resolver as ResolverModel;
use Cline\Cascade\Exception\ResolverNotFoundException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent-based resolver repository using Laravel's ORM.
 *
 * Provides resolver storage and retrieval using Laravel's Eloquent ORM,
 * with support for custom models and table names via ModelRegistry.
 * Stores resolver definitions as JSON in the database.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class EloquentRepository implements ResolverRepositoryInterface
{
    /**
     * @param ModelRegistry $registry Model registry for custom models/tables
     */
    public function __construct(
        private ModelRegistry $registry,
    ) {}

    /**
     * Get a resolver definition by name.
     *
     * @param  string                    $name The resolver name
     * @throws ResolverNotFoundException If the resolver is not found
     * @return array<string, mixed>      The resolver definition
     */
    public function get(string $name): array
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        /** @var null|ResolverModel $model */
        $model = $modelClass::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->first();

        if ($model === null) {
            throw ResolverNotFoundException::forName($name);
        }

        return $model->definition ?? [];
    }

    /**
     * Check if a resolver definition exists.
     *
     * @param  string $name The resolver name
     * @return bool   True if the resolver exists and is active, false otherwise
     */
    public function has(string $name): bool
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        return $modelClass::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all active resolver definitions.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function all(): array
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        /** @var Collection<int, ResolverModel> $models */
        $models = $modelClass::query()
            ->where('is_active', true)
            ->get();

        return $models->mapWithKeys(fn (ResolverModel $model): array => [$model->name => $model->definition ?? []])->all();
    }

    /**
     * Get multiple resolver definitions.
     *
     * @param  array<string>                       $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function getMany(array $names): array
    {
        if ($names === []) {
            return [];
        }

        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        /** @var Collection<int, ResolverModel> $models */
        $models = $modelClass::query()
            ->whereIn('name', $names)
            ->where('is_active', true)
            ->get();

        return $models->mapWithKeys(fn (ResolverModel $model): array => [$model->name => $model->definition ?? []])->all();
    }

    /**
     * Create or update a resolver definition.
     *
     * @param  string               $name        The resolver name
     * @param  array<string, mixed> $definition  The resolver definition
     * @param  null|string          $description Optional description
     * @return ResolverModel        The stored model
     */
    public function save(string $name, array $definition, ?string $description = null): ResolverModel
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        return $modelClass::query()->updateOrCreate(
            ['name' => $name],
            [
                'description' => $description,
                'definition' => $definition,
                'is_active' => true,
            ],
        );
    }

    /**
     * Delete a resolver by name.
     *
     * @param string $name The resolver name to delete
     *
     * @return bool True if deleted, false if not found
     */
    public function delete(string $name): bool
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        $deleted = $modelClass::query()
            ->where('name', $name)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Deactivate a resolver instead of deleting it.
     *
     * @param string $name The resolver name to deactivate
     *
     * @return bool True if deactivated, false if not found
     */
    public function deactivate(string $name): bool
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        $updated = $modelClass::query()
            ->where('name', $name)
            ->update(['is_active' => false]);

        return $updated > 0;
    }

    /**
     * Reactivate a deactivated resolver.
     *
     * @param string $name The resolver name to reactivate
     *
     * @return bool True if reactivated, false if not found
     */
    public function reactivate(string $name): bool
    {
        /** @var class-string<ResolverModel> $modelClass */
        $modelClass = $this->registry->resolverModel();

        $updated = $modelClass::query()
            ->where('name', $name)
            ->update(['is_active' => true]);

        return $updated > 0;
    }
}
