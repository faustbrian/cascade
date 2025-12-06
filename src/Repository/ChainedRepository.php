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
 * Try multiple repositories in order (fallback chain).
 *
 * Searches repositories in the order provided, returning the first match found.
 * Useful for implementing configuration overrides (local -> database -> defaults).
 */
final readonly class ChainedRepository implements ResolverRepositoryInterface
{
    /**
     * @param array<ResolverRepositoryInterface> $repositories Repositories to chain, in order of priority
     * @throws \InvalidArgumentException If no repositories are provided
     */
    public function __construct(
        private array $repositories,
    ) {
        throw_if($this->repositories === [], \InvalidArgumentException::class, 'ChainedRepository requires at least one repository');
    }

    /**
     * Get a resolver definition by name.
     *
     * Searches repositories in order, returning the first match found.
     *
     * @param string $name The resolver name
     * @return array<string, mixed> The resolver definition
     * @throws ResolverNotFoundException If the resolver is not found in any repository
     */
    public function get(string $name): array
    {
        foreach ($this->repositories as $repository) {
            if ($repository->has($name)) {
                return $repository->get($name);
            }
        }

        throw ResolverNotFoundException::forName($name);
    }

    /**
     * Check if a resolver definition exists.
     *
     * Returns true if any repository in the chain has the resolver.
     *
     * @param string $name The resolver name
     * @return bool True if the resolver exists, false otherwise
     */
    public function has(string $name): bool
    {
        return array_any($this->repositories, fn($repository): bool => $repository->has($name));
    }

    /**
     * Get all resolver definitions.
     *
     * Merges definitions from all repositories, with earlier repositories
     * taking precedence for duplicate names.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function all(): array
    {
        $resolvers = [];

        // Iterate in reverse order so earlier repositories override later ones
        foreach (\array_reverse($this->repositories) as $repository) {
            $resolvers = \array_merge($resolvers, $repository->all());
        }

        return $resolvers;
    }

    /**
     * Get multiple resolver definitions.
     *
     * For each name, returns the definition from the first repository that has it.
     *
     * @param array<string> $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function getMany(array $names): array
    {
        $result = [];
        $remaining = $names;

        foreach ($this->repositories as $repository) {
            if ($remaining === []) {
                break;
            }

            $found = $repository->getMany($remaining);
            $result = \array_merge($result, $found);

            // Remove found names from remaining
            $remaining = \array_diff($remaining, \array_keys($found));
        }

        return $result;
    }
}
