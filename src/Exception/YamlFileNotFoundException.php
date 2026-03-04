<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a YAML configuration file cannot be found at the specified path.
 *
 * This exception indicates that the file system does not contain a YAML file at the
 * requested location. Common causes include incorrect path configuration, missing
 * files in deployment, or typos in file names.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlFileNotFoundException extends CascadeException
{
    /**
     * Create exception for missing YAML file at specified path.
     *
     * @param  string $path The file system path where the YAML file was expected but not found
     * @return self   The configured exception instance with the missing file path
     */
    public static function atPath(string $path): self
    {
        return new self('YAML file not found: '.$path);
    }
}
