<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when reading a JSON file fails due to I/O or system errors.
 *
 * This exception occurs when file_get_contents() or similar read operations
 * fail unexpectedly. Unlike JsonFileNotReadableException (permissions) or
 * JsonFileNotFoundException (missing file), this indicates a system-level
 * failure such as disk I/O errors, network filesystem issues, or resource
 * exhaustion during the read operation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileReadFailedException extends CascadeException
{
    /**
     * Create exception for a JSON file that failed during the read operation.
     *
     * @param string $path The file path where the read operation failed
     */
    public static function atPath(string $path): self
    {
        return new self('Failed to read JSON file: '.$path);
    }
}
