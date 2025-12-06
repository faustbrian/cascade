<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use Throwable;

use function sprintf;

/**
 * Exception thrown when a YAML file contains invalid YAML.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidYamlFileException extends CascadeException
{
    public static function atPath(string $path, string $errorMessage, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Invalid YAML in file %s: %s', $path, $errorMessage),
            0,
            $previous,
        );
    }
}
