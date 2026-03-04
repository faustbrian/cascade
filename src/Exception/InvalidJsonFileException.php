<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use Throwable;

use function sprintf;

/**
 * Exception thrown when a JSON file contains invalid JSON syntax.
 *
 * This exception occurs when attempting to parse a JSON file that contains
 * malformed JSON, syntax errors, or encoding issues. Unlike file-not-found
 * or permission errors, this indicates the file exists and is readable but
 * its content cannot be parsed as valid JSON.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonFileException extends CascadeException
{
    /**
     * Create exception for invalid JSON syntax in file.
     *
     * @param string         $path         The absolute file path containing invalid JSON
     * @param string         $errorMessage The JSON parsing error message describing the syntax issue
     * @param null|Throwable $previous     Optional previous exception that caused the JSON parsing failure
     *
     * @return self Exception instance with file path and parsing error details
     */
    public static function atPath(string $path, string $errorMessage, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Invalid JSON in file %s: %s', $path, $errorMessage),
            0,
            $previous,
        );
    }
}
