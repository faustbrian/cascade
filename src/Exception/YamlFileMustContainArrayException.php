<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a YAML file contains invalid data structure.
 *
 * This exception is raised when a YAML file is successfully parsed but its root
 * element is not an array/mapping. Cascade requires YAML configuration files to
 * contain key-value mappings at the top level, not scalar values or other types.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlFileMustContainArrayException extends CascadeException
{
    /**
     * Create exception for YAML file with invalid root structure.
     *
     * @param  string $path The file system path to the YAML file with invalid structure
     * @return self   The configured exception instance with descriptive error message
     */
    public static function atPath(string $path): self
    {
        return new self('YAML file must contain a mapping/array: '.$path);
    }
}
