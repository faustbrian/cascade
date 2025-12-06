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
 * Load resolver definitions from a database table.
 *
 * Uses PDO to query a database table containing resolver definitions.
 * The definition column should contain JSON-encoded resolver definitions.
 */
final class DatabaseRepository implements ResolverRepositoryInterface
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $cachedResolvers = null;

    /**
     * @param \PDO $pdo Database connection
     * @param string $table Table name containing resolver definitions
     * @param string $nameColumn Column name for resolver name
     * @param string $definitionColumn Column name for resolver definition (JSON)
     * @param array<string, mixed> $conditions Additional WHERE conditions as key-value pairs
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $table = 'resolvers',
        private readonly string $nameColumn = 'name',
        private readonly string $definitionColumn = 'definition',
        private readonly array $conditions = [],
    ) {}

    /**
     * Get a resolver definition by name.
     *
     * @param string $name The resolver name
     * @return array<string, mixed> The resolver definition
     * @throws ResolverNotFoundException If the resolver is not found
     */
    public function get(string $name): array
    {
        $sql = $this->buildQuery([$this->nameColumn => $name]);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([$this->nameColumn => $name]);
        $stmt->execute($params);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw ResolverNotFoundException::forName($name);
        }

        \assert(\is_array($row));
        \assert(isset($row[$this->definitionColumn]));

        return $this->parseDefinition($row[$this->definitionColumn], $name);
    }

    /**
     * Check if a resolver definition exists.
     *
     * @param string $name The resolver name
     * @return bool True if the resolver exists, false otherwise
     */
    public function has(string $name): bool
    {
        $sql = $this->buildQuery([$this->nameColumn => $name], true);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([$this->nameColumn => $name]);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get all resolver definitions.
     *
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function all(): array
    {
        if ($this->cachedResolvers !== null) {
            return $this->cachedResolvers;
        }

        $sql = $this->buildQuery([]);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([]);
        $stmt->execute($params);

        /** @var array<string, array<string, mixed>> $resolvers */
        $resolvers = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            \assert(\is_array($row));
            \assert(isset($row[$this->nameColumn]));
            \assert(\is_string($row[$this->nameColumn]));
            \assert(isset($row[$this->definitionColumn]));

            $name = $row[$this->nameColumn];
            $resolvers[$name] = $this->parseDefinition($row[$this->definitionColumn], $name);
        }

        $this->cachedResolvers = $resolvers;

        return $resolvers;
    }

    /**
     * Get multiple resolver definitions.
     *
     * @param array<string> $names The resolver names to retrieve
     * @return array<string, array<string, mixed>> Map of resolver names to definitions
     */
    public function getMany(array $names): array
    {
        if ($names === []) {
            return [];
        }

        // Use all() and filter if we've already cached all resolvers
        if ($this->cachedResolvers !== null) {
            return \array_intersect_key($this->cachedResolvers, \array_flip($names));
        }

        $placeholders = \implode(',', \array_fill(0, \count($names), '?'));
        $sql = $this->buildQuery([], false, sprintf('AND %s IN (%s)', $this->nameColumn, $placeholders));

        $stmt = $this->pdo->prepare($sql);
        $params = \array_merge($this->buildParameters([]), $names);
        $stmt->execute($params);

        /** @var array<string, array<string, mixed>> $resolvers */
        $resolvers = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            \assert(\is_array($row));
            \assert(isset($row[$this->nameColumn]));
            \assert(\is_string($row[$this->nameColumn]));
            \assert(isset($row[$this->definitionColumn]));

            $name = $row[$this->nameColumn];
            $resolvers[$name] = $this->parseDefinition($row[$this->definitionColumn], $name);
        }

        return $resolvers;
    }

    /**
     * Build SQL query.
     *
     * @param array<string, mixed> $additionalConditions Additional WHERE conditions
     * @param bool $count Whether to build a COUNT query
     * @param string $suffix Additional SQL suffix
     * @return string The SQL query
     */
    private function buildQuery(array $additionalConditions = [], bool $count = false, string $suffix = ''): string
    {
        $select = $count ? 'COUNT(*)' : '*';
        $sql = sprintf('SELECT %s FROM %s', $select, $this->table);

        $conditions = \array_merge($this->conditions, $additionalConditions);

        if ($conditions !== []) {
            $whereClauses = [];
            foreach (array_keys($conditions) as $column) {
                $whereClauses[] = $column . ' = ?';
            }

            $sql .= ' WHERE ' . \implode(' AND ', $whereClauses);
        }

        if ($suffix !== '') {
            if ($conditions === []) {
                $sql .= ' WHERE 1=1';
            }

            $sql .= ' ' . $suffix;
        }

        return $sql;
    }

    /**
     * Build parameter array for prepared statement.
     *
     * @param array<string, mixed> $additionalConditions Additional WHERE conditions
     * @return array<mixed> Array of parameter values
     */
    private function buildParameters(array $additionalConditions = []): array
    {
        $conditions = \array_merge($this->conditions, $additionalConditions);
        return \array_values($conditions);
    }

    /**
     * Parse a JSON definition from the database.
     *
     * @param mixed $definition The raw definition value from the database
     * @param string $name The resolver name (for error messages)
     * @return array<string, mixed> The parsed definition
     * @throws \RuntimeException If the definition cannot be parsed
     */
    private function parseDefinition(mixed $definition, string $name): array
    {
        if (\is_array($definition)) {
            /** @var array<string, mixed> $definition */
            return $definition;
        }

        throw_unless(\is_string($definition), \RuntimeException::class, sprintf("Invalid definition for resolver '%s': expected JSON string or array", $name));

        try {
            $parsed = \json_decode($definition, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new \RuntimeException(
                sprintf("Invalid JSON definition for resolver '%s': %s", $name, $jsonException->getMessage()),
                0,
                $jsonException
            );
        }

        throw_unless(\is_array($parsed), \RuntimeException::class, sprintf("Invalid definition for resolver '%s': expected object/array", $name));

        /** @var array<string, mixed> $parsed */
        return $parsed;
    }
}
