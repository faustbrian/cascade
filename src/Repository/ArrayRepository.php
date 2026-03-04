<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\ResolverNotFoundException;

use function array_key_exists;

/**
 * In-memory resolver repository using an array for storage.
 *
 * This implementation provides fast, in-memory access to resolver definitions
 * without any I/O overhead. Ideal for testing scenarios, bootstrapping with
 * hardcoded defaults, or configuration-driven setups where all resolver
 * definitions are known at initialization time.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ArrayRepository implements ResolverRepositoryInterface
{
    /**
     * Create a new in-memory resolver repository.
     *
     * @param array<string, array<string, mixed>> $resolvers Map of resolver names to their configuration
     *                                                       definitions. Each key is a resolver identifier
     *                                                       and each value is an associative array containing
     *                                                       the resolver's configuration options and metadata.
     */
    public function __construct(
        private array $resolvers = [],
    ) {}

    /**
     * Retrieve a resolver definition by its name.
     *
     * @param  string                    $name The resolver identifier to retrieve
     * @throws ResolverNotFoundException When the requested resolver does not exist
     * @return array<string, mixed>      The resolver's configuration definition
     */
    public function get(string $name): array
    {
        if (!$this->has($name)) {
            throw ResolverNotFoundException::forName($name);
        }

        return $this->resolvers[$name];
    }

    /**
     * Check whether a resolver definition exists in the repository.
     *
     * @param  string $name The resolver identifier to check
     * @return bool   True if the resolver exists in the repository, false otherwise
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->resolvers);
    }

    /**
     * Retrieve all resolver definitions from the repository.
     *
     * @return array<string, array<string, mixed>> Complete map of resolver names to their definitions
     */
    public function all(): array
    {
        return $this->resolvers;
    }

    /**
     * Retrieve multiple resolver definitions efficiently.
     *
     * Silently skips any resolver names that don't exist in the repository,
     * returning only the definitions that are found.
     *
     * @param  array<string>                       $names List of resolver identifiers to retrieve
     * @return array<string, array<string, mixed>> Map of found resolver names to their definitions
     */
    public function getMany(array $names): array
    {
        $result = [];

        foreach ($names as $name) {
            if (!$this->has($name)) {
                continue;
            }

            $result[$name] = $this->resolvers[$name];
        }

        return $result;
    }
}
