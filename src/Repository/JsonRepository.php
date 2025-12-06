<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\InvalidJsonFileException;
use Cline\Cascade\Exception\JsonFileMustContainArrayException;
use Cline\Cascade\Exception\JsonFileNotFoundException;
use Cline\Cascade\Exception\JsonFileNotReadableException;
use Cline\Cascade\Exception\JsonFileReadFailedException;
use Cline\Cascade\Exception\ResolverNotFoundException;
use JsonException;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

use function array_key_exists;
use function array_merge;
use function ctype_alpha;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;
use function json_decode;
use function mb_rtrim;
use function mb_strlen;

/**
 * Load resolver definitions from JSON file(s).
 *
 * Supports loading from a single file or multiple files that are merged together.
 * Later files override earlier files for duplicate resolver names.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class JsonRepository implements ResolverRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $resolvers;

    /**
     * @param  array<string>|string              $paths    Single file path or array of file paths
     * @param  null|string                       $basePath Optional base path to prepend to relative paths
     * @throws InvalidJsonFileException          If a file contains invalid JSON
     * @throws JsonFileMustContainArrayException If a file does not contain an array
     * @throws JsonFileNotFoundException         If a file cannot be found
     * @throws JsonFileNotReadableException      If a file is not readable
     * @throws JsonFileReadFailedException       If reading a file fails
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

    /**
     * Load resolver definitions from file(s).
     *
     * @param  array<string>|string                $paths Single file path or array of file paths
     * @throws InvalidJsonFileException            If a file contains invalid JSON
     * @throws JsonFileMustContainArrayException   If a file does not contain an array
     * @throws JsonFileNotFoundException           If a file cannot be found
     * @throws JsonFileNotReadableException        If a file is not readable
     * @throws JsonFileReadFailedException         If reading a file fails
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    private function loadResolvers(string|array $paths): array
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $resolvers = [];

        foreach ($paths as $path) {
            $fullPath = $this->resolvePath($path);

            if (!file_exists($fullPath)) {
                throw JsonFileNotFoundException::atPath($fullPath);
            }

            if (!is_readable($fullPath)) {
                throw JsonFileNotReadableException::atPath($fullPath);
            }

            $contents = file_get_contents($fullPath);

            if ($contents === false) {
                throw JsonFileReadFailedException::atPath($fullPath);
            }

            try {
                $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw InvalidJsonFileException::atPath($fullPath, $e->getMessage(), $e);
            }

            if (!is_array($data)) {
                throw JsonFileMustContainArrayException::atPath($fullPath);
            }

            // Merge resolvers, later files override earlier ones
            /** @var array<string, array<string, mixed>> $data */
            $resolvers = array_merge($resolvers, $data);
        }

        return $resolvers;
    }

    /**
     * Resolve a path with the base path if provided.
     *
     * @param  string $path The path to resolve
     * @return string The resolved absolute path
     */
    private function resolvePath(string $path): string
    {
        if ($this->basePath === null || $this->isAbsolutePath($path)) {
            return $path;
        }

        return mb_rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Check if a path is absolute.
     *
     * @param  string $path The path to check
     * @return bool   True if the path is absolute, false otherwise
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix/Linux absolute path
        if ($path[0] === '/') {
            return true;
        }

        // Windows absolute path (e.g., C:\, D:\)
        return mb_strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/');
    }
}
