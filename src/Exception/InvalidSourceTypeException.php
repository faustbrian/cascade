<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use function implode;
use function sprintf;

/**
 * Exception thrown when a source type is not recognized or supported.
 *
 * Sources are categorized by type (e.g., 'env', 'file', 'database', 'array')
 * to determine how values should be retrieved and processed. This exception
 * occurs when attempting to register a source with an unrecognized type value,
 * preventing proper source initialization and value resolution.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSourceTypeException extends SourceException
{
    /**
     * Create exception for invalid source type with list of valid alternatives.
     *
     * @param string        $type       The invalid source type that was provided
     * @param array<string> $validTypes List of recognized source type identifiers that are supported
     *                                  by the current resolver configuration, used to help developers
     *                                  identify the correct type value to use.
     *
     * @return self Exception instance with invalid type and valid alternatives listed
     */
    public static function forType(string $type, array $validTypes): self
    {
        $valid = implode(', ', $validTypes);

        return new self(
            sprintf("Invalid source type '%s'. Valid types are: %s", $type, $valid),
        );
    }
}
