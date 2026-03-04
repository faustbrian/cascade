<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception class for resolution failures - deprecated in favor of specific exceptions.
 *
 * This exception was previously used as a generic container for all resolution
 * failures. It has been replaced by more specific exception types that provide
 * clearer context about the nature of resolution failures. Existing code should
 * migrate to use ResolutionFailedForKeyException or NoSourcesAvailableException
 * depending on the specific failure scenario.
 *
 * @author Brian Faust <brian@cline.sh>
 * @deprecated Use specific exception classes instead:
 *             - ResolutionFailedForKeyException for key-specific resolution failures
 *             - NoSourcesAvailableException when no sources are configured
 */
final class ResolutionFailedException extends CascadeException
{
    // This class is deprecated and kept only for backward compatibility.
    // All factory methods have been moved to specific exception classes.
}
