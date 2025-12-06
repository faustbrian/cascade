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
 * Thrown when a source configuration is missing a required key.
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingSourceConfigurationException extends SourceException
{
    public static function forKey(string $key): self
    {
        return new self(
            sprintf("Source configuration is missing required key: '%s'", $key),
        );
    }
}
