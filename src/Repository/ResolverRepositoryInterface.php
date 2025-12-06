<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\ResolverNotFoundException;

/**
 * Interface for resolver definition repositories.
 *
 * Repositories store and retrieve resolver chain definitions (configuration arrays),
 * not Resolver instances. The definitions are used by Cascade to build resolver
 * chains at runtime. Implementations may load definitions from JSON, YAML, PHP arrays,
 * databases, or any other storage mechanism.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ResolverRepositoryInterface
{
    /**
     * Get a resolver definition by name.
     *
     * @param  string                    $name The resolver name to retrieve
     * @throws ResolverNotFoundException If the resolver name does not exist in the repository
     * @return array<string, mixed>      The resolver configuration array containing source definitions,
     *                                   priorities, and other settings needed to build the resolver chain
     */
    public function get(string $name): array;

    /**
     * Check if a resolver definition exists.
     *
     * @param  string $name The resolver name to check for existence
     * @return bool   True if the resolver exists in the repository, false otherwise
     */
    public function has(string $name): bool;

    /**
     * Get all resolver definitions.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to their configuration definitions.
     *                                             Returns all resolvers stored in the repository.
     */
    public function all(): array;

    /**
     * Get multiple resolver definitions.
     *
     * Implementations should skip non-existent resolver names rather than throwing exceptions,
     * allowing for flexible queries where some resolvers may not exist.
     *
     * @param  array<string>                       $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to their definitions. Only includes
     *                                             resolvers that exist in the repository.
     */
    public function getMany(array $names): array;
}
