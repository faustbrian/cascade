<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when a JSON file does not contain an array.
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileMustContainArrayException extends CascadeException
{
    public static function atPath(string $path): self
    {
        return new self('JSON file must contain an object/array: '.$path);
    }
}
