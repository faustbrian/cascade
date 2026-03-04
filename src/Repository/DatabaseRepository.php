<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Repository;

use Cline\Cascade\Exception\InvalidDefinitionTypeException;
use Cline\Cascade\Exception\InvalidJsonDefinitionException;
use Cline\Cascade\Exception\InvalidParsedDefinitionException;
use Cline\Cascade\Exception\ResolverNotFoundException;
use JsonException;
use PDO;

use const JSON_THROW_ON_ERROR;

use function array_fill;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_merge;
use function array_values;
use function assert;
use function count;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;

/**
 * Repository that loads resolver definitions from a database table via PDO.
 *
 * This implementation queries a database table where each row represents a resolver
 * definition. The definition column must contain JSON-encoded configuration data.
 * Supports configurable table schema and additional WHERE conditions for multi-tenant
 * or filtered resolver sets. Results from all() are cached in memory to avoid
 * repeated queries during a single request lifecycle.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DatabaseRepository implements ResolverRepositoryInterface
{
    /**
     * In-memory cache of all resolvers loaded by the all() method.
     *
     * @var null|array<string, array<string, mixed>>
     */
    private ?array $cachedResolvers = null;

    /**
     * Create a new database-backed resolver repository.
     *
     * @param PDO                  $pdo              PDO database connection used for all queries.
     *                                               Must be properly configured with error mode and
     *                                               fetch mode before passing to this constructor.
     * @param string               $table            Name of the database table containing resolver definitions.
     *                                               Defaults to 'resolvers' but can be customized for specific
     *                                               table naming conventions.
     * @param string               $nameColumn       Name of the column storing the resolver identifier.
     *                                               Used in WHERE clauses to filter by resolver name.
     * @param string               $definitionColumn Name of the column storing the JSON-encoded resolver
     *                                               definition. Must contain valid JSON representing an
     *                                               associative array of configuration options.
     * @param array<string, mixed> $conditions       Additional WHERE clause conditions applied to all queries.
     *                                               Useful for multi-tenant scenarios or filtering by status,
     *                                               environment, or other criteria. Keys are column names,
     *                                               values are the expected values.
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'resolvers',
        private readonly string $nameColumn = 'name',
        private readonly string $definitionColumn = 'definition',
        private readonly array $conditions = [],
    ) {}

    /**
     * Retrieve a single resolver definition from the database by name.
     *
     * Executes a SELECT query with WHERE conditions to fetch the specific resolver.
     * Parses the JSON-encoded definition column and returns the configuration array.
     *
     * @param  string                    $name The resolver identifier to retrieve
     * @throws ResolverNotFoundException When the resolver is not found in the database
     * @return array<string, mixed>      The parsed resolver configuration definition
     */
    public function get(string $name): array
    {
        $sql = $this->buildQuery([$this->nameColumn => $name]);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([$this->nameColumn => $name]);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw ResolverNotFoundException::forName($name);
        }

        assert(is_array($row));
        assert(isset($row[$this->definitionColumn]));

        return $this->parseDefinition($row[$this->definitionColumn], $name);
    }

    /**
     * Check whether a resolver exists in the database.
     *
     * Executes a COUNT query to efficiently determine existence without
     * fetching the full definition.
     *
     * @param  string $name The resolver identifier to check
     * @return bool   True if the resolver exists in the database, false otherwise
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
     * Retrieve all resolver definitions from the database with in-memory caching.
     *
     * Loads all resolver rows from the database table, parses their JSON definitions,
     * and caches the result in memory. Subsequent calls return the cached data without
     * additional database queries.
     *
     * @return array<string, array<string, mixed>> Complete map of resolver names to their parsed definitions
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

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            assert(is_array($row));
            assert(isset($row[$this->nameColumn]));
            assert(is_string($row[$this->nameColumn]));
            assert(isset($row[$this->definitionColumn]));

            $name = $row[$this->nameColumn];
            $resolvers[$name] = $this->parseDefinition($row[$this->definitionColumn], $name);
        }

        $this->cachedResolvers = $resolvers;

        return $resolvers;
    }

    /**
     * Retrieve multiple resolver definitions efficiently with a single query.
     *
     * Uses an IN clause to fetch all requested resolvers in one database query.
     * If all resolvers are already cached via all(), returns the filtered cache
     * instead of querying the database.
     *
     * @param  array<string>                       $names List of resolver identifiers to retrieve
     * @return array<string, array<string, mixed>> Map of found resolver names to their parsed definitions
     */
    public function getMany(array $names): array
    {
        if ($names === []) {
            return [];
        }

        // Use all() and filter if we've already cached all resolvers
        if ($this->cachedResolvers !== null) {
            return array_intersect_key($this->cachedResolvers, array_flip($names));
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $sql = $this->buildQuery([], false, sprintf('AND %s IN (%s)', $this->nameColumn, $placeholders));

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge($this->buildParameters([]), $names);
        $stmt->execute($params);

        /** @var array<string, array<string, mixed>> $resolvers */
        $resolvers = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            assert(is_array($row));
            assert(isset($row[$this->nameColumn]));
            assert(is_string($row[$this->nameColumn]));
            assert(isset($row[$this->definitionColumn]));

            $name = $row[$this->nameColumn];
            $resolvers[$name] = $this->parseDefinition($row[$this->definitionColumn], $name);
        }

        return $resolvers;
    }

    /**
     * Build a parameterized SQL query with dynamic conditions.
     *
     * Constructs SELECT or COUNT queries with WHERE clauses based on configured
     * conditions and additional runtime conditions. Uses prepared statement
     * placeholders for all values to prevent SQL injection.
     *
     * @param  array<string, mixed> $additionalConditions Extra WHERE conditions to merge with configured conditions
     * @param  bool                 $count                When true, generates a COUNT(*) query instead of SELECT *
     * @param  string               $suffix               Additional SQL to append after WHERE clause (e.g., ORDER BY, LIMIT)
     * @return string               The complete parameterized SQL query string
     */
    private function buildQuery(array $additionalConditions = [], bool $count = false, string $suffix = ''): string
    {
        $select = $count ? 'COUNT(*)' : '*';
        $sql = sprintf('SELECT %s FROM %s', $select, $this->table);

        $conditions = array_merge($this->conditions, $additionalConditions);

        if ($conditions !== []) {
            $whereClauses = [];

            foreach (array_keys($conditions) as $column) {
                $whereClauses[] = $column.' = ?';
            }

            $sql .= ' WHERE '.implode(' AND ', $whereClauses);
        }

        if ($suffix !== '') {
            if ($conditions === []) {
                $sql .= ' WHERE 1=1';
            }

            $sql .= ' '.$suffix;
        }

        return $sql;
    }

    /**
     * Build the parameter values array for a prepared statement.
     *
     * Merges configured conditions with additional conditions and extracts their
     * values in the correct order for PDO parameter binding.
     *
     * @param  array<string, mixed> $additionalConditions Extra WHERE conditions to merge with configured conditions
     * @return array<mixed>         Ordered array of parameter values for prepared statement execution
     */
    private function buildParameters(array $additionalConditions = []): array
    {
        $conditions = array_merge($this->conditions, $additionalConditions);

        return array_values($conditions);
    }

    /**
     * Parse and validate a resolver definition from the database.
     *
     * Handles both pre-parsed array definitions (from some PDO drivers) and
     * JSON-encoded string definitions. Validates that the final result is an
     * associative array suitable for resolver configuration.
     *
     * @param  mixed                            $definition The raw definition value from the database column
     * @param  string                           $name       The resolver identifier (used in error messages)
     * @throws InvalidDefinitionTypeException   When the definition is neither a string nor an array
     * @throws InvalidJsonDefinitionException   When JSON decoding fails due to invalid syntax
     * @throws InvalidParsedDefinitionException When the parsed JSON is not an associative array
     * @return array<string, mixed>             The validated and parsed resolver configuration definition
     */
    private function parseDefinition(mixed $definition, string $name): array
    {
        if (is_array($definition)) {
            /** @var array<string, mixed> $definition */
            return $definition;
        }

        if (!is_string($definition)) {
            throw InvalidDefinitionTypeException::forResolver($name);
        }

        try {
            $parsed = json_decode($definition, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidJsonDefinitionException::forResolver($name, $jsonException->getMessage(), $jsonException);
        }

        if (!is_array($parsed)) {
            throw InvalidParsedDefinitionException::forResolver($name);
        }

        /** @var array<string, mixed> $parsed */
        return $parsed;
    }
}
