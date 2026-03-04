<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a JSON file contains a scalar value instead of array/object.
 *
 * Configuration sources expect JSON files to contain array or object structures
 * that can be traversed for key-value pairs. This exception occurs when a JSON
 * file is valid but contains a scalar value (string, number, boolean, null)
 * at the root level, making it unsuitable for configuration data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileMustContainArrayException extends CascadeException
{
    /**
     * Create exception for JSON file with scalar root value.
     *
     * @param string $path The absolute file path to the JSON file containing a scalar value
     *
     * @return self Exception instance with file path information
     */
    public static function atPath(string $path): self
    {
        return new self('JSON file must contain an object/array: '.$path);
    }
}
