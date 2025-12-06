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
 * Exception thrown when a database definition contains invalid JSON.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonDefinitionException extends CascadeException
{
    public static function forResolver(string $name, string $errorMessage, ?Throwable $previous = null): self
    {
        return new self(
            sprintf("Invalid JSON definition for resolver '%s': %s", $name, $errorMessage),
            0,
            $previous,
        );
    }
}
