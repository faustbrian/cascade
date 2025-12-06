<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when reading a JSON file fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileReadFailedException extends CascadeException
{
    public static function atPath(string $path): self
    {
        return new self('Failed to read JSON file: '.$path);
    }
}
