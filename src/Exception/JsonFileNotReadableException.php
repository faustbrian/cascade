<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a JSON file exists but is not readable.
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileNotReadableException extends CascadeException
{
    public static function atPath(string $path): self
    {
        return new self('JSON file not readable: '.$path);
    }
}
