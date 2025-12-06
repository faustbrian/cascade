<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Deprecated base exception for source configuration errors.
 *
 * This class has been superseded by more specific exception types that provide
 * clearer error messages and better debugging context. New code should use the
 * appropriate specific exception class based on the type of validation failure.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @deprecated Use specific exception classes instead:
 *             - MissingSourceConfigurationException for missing required configuration keys
 *             - InvalidSourceTypeException for invalid source type values
 *             - InvalidSourceNameException for invalid or empty source names
 *             - DuplicateSourceNameException for duplicate source name registration
 *             - InvalidSourcePriorityException for invalid priority values
 */
final class InvalidSourceException extends SourceException
{
    // This class is deprecated and kept only for backward compatibility.
    // All factory methods have been moved to specific exception classes.
}
