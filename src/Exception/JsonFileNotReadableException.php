<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a JSON file exists but cannot be read due to permission issues.
 *
 * This exception occurs when the file exists in the filesystem but the current
 * process lacks sufficient permissions to read it. Common causes include
 * incorrect file ownership, restrictive file permissions (e.g., 000 or 200),
 * or SELinux/AppArmor security policies blocking access.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileNotReadableException extends CascadeException
{
    /**
     * Create exception for a JSON file that exists but cannot be read.
     *
     * @param string $path The file path that lacks read permissions
     */
    public static function atPath(string $path): self
    {
        return new self('JSON file not readable: '.$path);
    }
}
