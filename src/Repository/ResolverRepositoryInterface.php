<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\ResolverNotFoundException;

/**
 * Interface for resolver definition repositories.
 *
 * Repositories store and retrieve resolver chain definitions (arrays),
 * not Resolver instances. The definitions are used by Cascade to build
 * resolver chains at runtime.
 */
interface ResolverRepositoryInterface
{
    /**
     * Get a resolver definition by name.
     *
     * @param string $name The resolver name
     * @return array<string, mixed> The resolver definition
     * @throws ResolverNotFoundException If the resolver is not found
     */
    public function get(string $name): array;

    /**
     * Check if a resolver definition exists.
     *
     * @param string $name The resolver name
     * @return bool True if the resolver exists, false otherwise
     */
    public function has(string $name): bool;

    /**
     * Get all resolver definitions.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function all(): array;

    /**
     * Get multiple resolver definitions.
     *
     * @param array<string> $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function getMany(array $names): array;
}
