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
 * Exception thrown when a source configuration array is missing a required key.
 *
 * This exception occurs during source instantiation when a configuration array
 * lacks mandatory keys such as 'type', 'name', or other required fields. This
 * typically indicates incomplete configuration data or invalid source definitions
 * in configuration files or programmatic source registration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingSourceConfigurationException extends SourceException
{
    /**
     * Create exception for a missing required configuration key.
     *
     * @param string $key The required configuration key that was not found in the source definition
     */
    public static function forKey(string $key): self
    {
        return new self(
            sprintf("Source configuration is missing required key: '%s'", $key),
        );
    }
}
