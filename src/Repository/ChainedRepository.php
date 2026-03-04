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
 * Repository that searches multiple repositories in priority order with fallback support.
 *
 * This implementation enables configuration layering by searching through multiple
 * repositories sequentially until a match is found. Common use cases include
 * configuration overrides where local settings take precedence over database
 * settings, which take precedence over default values (local → database → defaults).
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ChainedRepository implements ResolverRepositoryInterface
{
    /**
     * Create a new chained repository with fallback support.
     *
     * @param  array<ResolverRepositoryInterface> $repositories List of repositories to search in priority order.
     *                                                          The first repository in the array has highest priority,
     *                                                          and repositories are searched sequentially until a match
     *                                                          is found. Must contain at least one repository.
     * @throws EmptyChainedRepositoryException    When the repositories array is empty
     */
    public function __construct(
        private array $repositories,
    ) {
        if ($this->repositories === []) {
            throw EmptyChainedRepositoryException::create();
        }
    }

    /**
     * Retrieve a resolver definition by searching repositories in priority order.
     *
     * Iterates through the repository chain and returns the definition from the
     * first repository that contains the requested resolver.
     *
     * @param  string                    $name The resolver identifier to retrieve
     * @throws ResolverNotFoundException When the resolver is not found in any repository in the chain
     * @return array<string, mixed>      The resolver's configuration definition from the highest priority repository
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
     * Check whether a resolver exists in any repository in the chain.
     *
     * Returns true if at least one repository in the chain contains the
     * requested resolver, regardless of priority order.
     *
     * @param  string $name The resolver identifier to check
     * @return bool   True if any repository contains the resolver, false otherwise
     */
    public function has(string $name): bool
    {
        return array_any($this->repositories, fn ($repository): bool => $repository->has($name));
    }

    /**
     * Retrieve all resolver definitions merged from all repositories.
     *
     * Combines definitions from all repositories in the chain, with higher-priority
     * repositories (earlier in the chain) overriding definitions from lower-priority
     * repositories for duplicate resolver names.
     *
     * @return array<string, array<string, mixed>> Merged map of resolver names to their definitions
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
     * Retrieve multiple resolver definitions efficiently from the repository chain.
     *
     * For each requested resolver, returns the definition from the highest-priority
     * repository that contains it. Uses an optimized algorithm that queries repositories
     * sequentially and stops searching for each resolver once found.
     *
     * @param  array<string>                       $names List of resolver identifiers to retrieve
     * @return array<string, array<string, mixed>> Map of found resolver names to their definitions
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
