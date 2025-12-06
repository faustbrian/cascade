<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\EmptyChainedRepositoryException;
use Cline\Cascade\Exception\ResolverNotFoundException;

use function array_any;
use function array_diff;
use function array_keys;
use function array_merge;
use function array_reverse;

/**
 * Try multiple repositories in order (fallback chain).
 *
 * Searches repositories in the order provided, returning the first match found.
 * Useful for implementing configuration overrides (local -> database -> defaults).
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ChainedRepository implements ResolverRepositoryInterface
{
    /**
     * @param  array<ResolverRepositoryInterface> $repositories Repositories to chain, in order of priority
     * @throws EmptyChainedRepositoryException    If no repositories are provided
     */
    public function __construct(
        private array $repositories,
    ) {
        if ($this->repositories === []) {
            throw EmptyChainedRepositoryException::create();
        }
    }

    /**
     * Get a resolver definition by name.
     *
     * Searches repositories in order, returning the first match found.
     *
     * @param  string                    $name The resolver name
     * @throws ResolverNotFoundException If the resolver is not found in any repository
     * @return array<string, mixed>      The resolver definition
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
     * @param  string $name The resolver name
     * @return bool   True if the resolver exists, false otherwise
     */
    public function has(string $name): bool
    {
        return array_any($this->repositories, fn ($repository): bool => $repository->has($name));
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
        foreach (array_reverse($this->repositories) as $repository) {
            $resolvers = array_merge($resolvers, $repository->all());
        }

        return $resolvers;
    }

    /**
     * Get multiple resolver definitions.
     *
     * For each name, returns the definition from the first repository that has it.
     *
     * @param  array<string>                       $names The resolver names to retrieve
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
            $result = array_merge($result, $found);

            // Remove found names from remaining
            $remaining = array_diff($remaining, array_keys($found));
        }

        return $result;
    }
}
