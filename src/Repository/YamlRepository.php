<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\InvalidYamlFileException;
use Cline\Cascade\Exception\ResolverNotFoundException;
use Cline\Cascade\Exception\YamlFileMustContainArrayException;
use Cline\Cascade\Exception\YamlFileNotFoundException;
use Cline\Cascade\Exception\YamlFileNotReadableException;
use Cline\Cascade\Exception\YamlPackageRequiredException;
use Exception;
use Symfony\Component\Yaml\Yaml;

use const DIRECTORY_SEPARATOR;

use function array_key_exists;
use function array_merge;
use function class_exists;
use function ctype_alpha;
use function file_exists;
use function is_array;
use function is_readable;
use function mb_rtrim;
use function mb_strlen;

/**
 * Load resolver definitions from YAML file(s).
 *
 * Requires symfony/yaml package to be installed as an optional dependency.
 * Supports loading from a single file or multiple files that are merged together.
 * When multiple files are provided, later files override earlier files for duplicate
 * resolver names, enabling layered configuration strategies.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class YamlRepository implements ResolverRepositoryInterface
{
    /**
     * Map of resolver names to their configuration definitions.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $resolvers;

    /**
     * Create a new YAML repository from file path(s).
     *
     * @param  array<string>|string         $paths    Single file path or array of file paths to load resolver
     *                                                definitions from. When multiple paths are provided, files are
     *                                                merged in order with later files taking precedence for
     *                                                duplicate keys.
     * @param  null|string                  $basePath Optional base directory path to prepend to relative file paths.
     *                                                Absolute paths in $paths are not affected. Useful for making
     *                                                resolver configuration files portable across environments.
     * @throws YamlPackageRequiredException If symfony/yaml package is not installed
     */
    public function __construct(
        string|array $paths,
        private ?string $basePath = null,
    ) {
        if (!class_exists(Yaml::class)) {
            throw YamlPackageRequiredException::create();
        }

        $this->resolvers = $this->loadResolvers($paths);
    }

    /**
     * Get a resolver definition by name.
     *
     * @param  string                    $name The resolver name to retrieve
     * @throws ResolverNotFoundException If the resolver name does not exist in the repository
     * @return array<string, mixed>      The resolver configuration array containing source definitions and settings
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
     * Non-existent resolver names are silently skipped rather than throwing exceptions.
     * This allows for flexible resolver queries where some resolvers may not exist.
     *
     * @param  array<string>                       $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to their definitions. Only resolvers
     *                                             that exist in the repository are included in the result.
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
     * Iterates through all provided paths, resolving relative paths against the base path,
     * validating file accessibility, parsing YAML content, and merging resolver definitions.
     * Files are processed in order with later files overriding earlier ones for duplicate keys.
     *
     * @param  array<string>|string                $paths Single file path or array of file paths
     * @throws InvalidYamlFileException            If a file contains invalid YAML syntax
     * @throws YamlFileMustContainArrayException   If a file's root value is not an array
     * @throws YamlFileNotFoundException           If a file cannot be found at the resolved path
     * @throws YamlFileNotReadableException        If a file exists but lacks read permissions
     * @return array<string, array<string, mixed>> Map of resolver names to their configuration definitions
     */
    private function loadResolvers(string|array $paths): array
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $resolvers = [];

        foreach ($paths as $path) {
            $fullPath = $this->resolvePath($path);

            if (!file_exists($fullPath)) {
                throw YamlFileNotFoundException::atPath($fullPath);
            }

            if (!is_readable($fullPath)) {
                throw YamlFileNotReadableException::atPath($fullPath);
            }

            try {
                $data = Yaml::parseFile($fullPath);
            } catch (Exception $e) {
                throw InvalidYamlFileException::atPath($fullPath, $e->getMessage(), $e);
            }

            if (!is_array($data)) {
                throw YamlFileMustContainArrayException::atPath($fullPath);
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
     * If the path is already absolute or no base path was configured, returns the path unchanged.
     * Otherwise, concatenates the base path and the relative path with proper directory separators.
     *
     * @param  string $path The path to resolve, either absolute or relative
     * @return string The resolved absolute path suitable for file system operations
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
     * Supports both Unix/Linux absolute paths (starting with /) and Windows absolute
     * paths (drive letter followed by colon and backslash, e.g., C:\).
     *
     * @param  string $path The path to check
     * @return bool   True if the path is absolute, false if relative
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
