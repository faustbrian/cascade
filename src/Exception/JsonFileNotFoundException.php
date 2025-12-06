<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a JSON configuration file cannot be found at the specified path.
 *
 * This exception occurs during source initialization when attempting to load
 * a JSON file that does not exist in the filesystem. Common causes include
 * incorrect file paths, missing configuration files, or deployment issues
 * where configuration files were not properly copied.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileNotFoundException extends CascadeException
{
    /**
     * Create exception for a JSON file that cannot be found at the given path.
     *
     * @param string $path The absolute or relative file path that was not found
     */
    public static function atPath(string $path): self
    {
        return new self('JSON file not found: '.$path);
    }
}
