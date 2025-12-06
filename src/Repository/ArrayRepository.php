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
 * In-memory resolver repository using an array.
 *
 * Useful for testing or configuration-driven setups where resolver
 * definitions are provided directly as arrays.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ArrayRepository implements ResolverRepositoryInterface
{
    /**
     * @param array<string, array<string, mixed>> $resolvers Map of resolver names to definitions
     */
    public function __construct(
        private array $resolvers = [],
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
        if (!$this->has($name)) {
            throw ResolverNotFoundException::forName($name);
        }

        return $this->resolvers[$name];
    }

    /**
     * Check if a resolver definition exists.
     *
     * @param  string $name The resolver name
     * @return bool   True if the resolver exists, false otherwise
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->resolvers);
    }

    /**
     * Get all resolver definitions.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function all(): array
    {
        return $this->resolvers;
    }

    /**
     * Get multiple resolver definitions.
     *
     * @param  array<string>                       $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
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
