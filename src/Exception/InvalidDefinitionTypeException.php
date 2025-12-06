<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use function sprintf;

/**
 * Exception thrown when a resolver definition is not a string or array.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDefinitionTypeException extends CascadeException
{
    public static function forResolver(string $name): self
    {
        return new self(
            sprintf("Invalid definition for resolver '%s': expected JSON string or array", $name),
        );
    }
}
