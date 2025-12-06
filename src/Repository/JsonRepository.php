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
 * Load resolver definitions from JSON file(s).
 *
 * Supports loading from a single file or multiple files that are merged together.
 * Later files override earlier files for duplicate resolver names.
 */
final readonly class JsonRepository implements ResolverRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $resolvers;

    /**
     * @param string|array<string> $paths Single file path or array of file paths
     * @param string|null $basePath Optional base path to prepend to relative paths
     * @throws \RuntimeException If a file cannot be read or parsed
     */
    public function __construct(
        string|array $paths,
        private ?string $basePath = null,
    ) {
        $this->resolvers = $this->loadResolvers($paths);
    }

    /**
     * Get a resolver definition by name.
     *
     * @param string $name The resolver name
     * @return array<string, mixed> The resolver definition
     * @throws ResolverNotFoundException If the resolver is not found
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
     * @param string $name The resolver name
     * @return bool True if the resolver exists, false otherwise
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->resolvers);
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
     * @param array<string> $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function getMany(array $names): array
    {
        $result = [];

        foreach ($names as $name) {
            if ($this->has($name)) {
                $result[$name] = $this->resolvers[$name];
            }
        }

        return $result;
    }

    /**
     * Load resolver definitions from file(s).
     *
     * @param string|array<string> $paths Single file path or array of file paths
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     * @throws \RuntimeException If a file cannot be read or parsed
     */
    private function loadResolvers(string|array $paths): array
    {
        $paths = \is_array($paths) ? $paths : [$paths];
        $resolvers = [];

        foreach ($paths as $path) {
            $fullPath = $this->resolvePath($path);

            throw_unless(\file_exists($fullPath), \RuntimeException::class, 'JSON file not found: ' . $fullPath);

            throw_unless(\is_readable($fullPath), \RuntimeException::class, 'JSON file not readable: ' . $fullPath);

            $contents = \file_get_contents($fullPath);
            throw_if($contents === false, \RuntimeException::class, 'Failed to read JSON file: ' . $fullPath);

            try {
                $data = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException(sprintf('Invalid JSON in file %s: %s', $fullPath, $e->getMessage()), 0, $e);
            }

            throw_unless(\is_array($data), \RuntimeException::class, 'JSON file must contain an object/array: ' . $fullPath);

            // Merge resolvers, later files override earlier ones
            /** @var array<string, array<string, mixed>> $data */
            $resolvers = \array_merge($resolvers, $data);
        }

        return $resolvers;
    }

    /**
     * Resolve a path with the base path if provided.
     *
     * @param string $path The path to resolve
     * @return string The resolved absolute path
     */
    private function resolvePath(string $path): string
    {
        if ($this->basePath === null || $this->isAbsolutePath($path)) {
            return $path;
        }

        return \rtrim($this->basePath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Check if a path is absolute.
     *
     * @param string $path The path to check
     * @return bool True if the path is absolute, false otherwise
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix/Linux absolute path
        if ($path[0] === '/') {
            return true;
        }

        // Windows absolute path (e.g., C:\, D:\)
        return \strlen($path) >= 3 && \ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/');
    }
}
