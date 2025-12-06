<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Thrown when a source configuration is invalid.
 */
final class InvalidSourceException extends SourceException
{
    /**
     * Create exception for missing required configuration.
     *
     * @param string $key The missing configuration key
     */
    public static function missingConfiguration(string $key): self
    {
        return new self(
            sprintf("Source configuration is missing required key: '%s'", $key),
        );
    }

    /**
     * Create exception for invalid source type.
     *
     * @param string $type The invalid type provided
     * @param array<string> $validTypes List of valid types
     */
    public static function invalidType(string $type, array $validTypes): self
    {
        $valid = \implode(', ', $validTypes);

        return new self(
            sprintf("Invalid source type '%s'. Valid types are: %s", $type, $valid),
        );
    }

    /**
     * Create exception for invalid source name.
     *
     * @param string $name The invalid source name
     */
    public static function invalidName(string $name): self
    {
        return new self(
            sprintf("Invalid source name '%s'. Source names must be non-empty strings.", $name),
        );
    }

    /**
     * Create exception for duplicate source name.
     *
     * @param string $name The duplicate source name
     */
    public static function duplicateName(string $name): self
    {
        return new self(
            sprintf("Source with name '%s' is already registered.", $name),
        );
    }

    /**
     * Create exception for invalid priority value.
     *
     * @param mixed $priority The invalid priority value
     */
    public static function invalidPriority(mixed $priority): self
    {
        $type = \get_debug_type($priority);

        return new self(
            sprintf('Invalid source priority. Expected integer, got %s.', $type),
        );
    }
}
