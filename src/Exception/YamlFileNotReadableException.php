<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a YAML file exists but cannot be read due to permissions.
 *
 * This exception indicates that the file exists at the specified path but the
 * application lacks sufficient file system permissions to read its contents.
 * Common causes include incorrect file ownership, restrictive permissions, or
 * SELinux/AppArmor policies blocking access.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlFileNotReadableException extends CascadeException
{
    /**
     * Create exception for YAML file with insufficient read permissions.
     *
     * @param  string $path The file system path to the YAML file that cannot be read
     * @return self   The configured exception instance indicating permission issue
     */
    public static function atPath(string $path): self
    {
        return new self('YAML file not readable: '.$path);
    }
}
