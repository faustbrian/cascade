<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * @author Brian Faust <brian@cline.sh>
 * @deprecated Use specific exception classes instead:
 * - ResolutionFailedForKeyException
 * - NoSourcesAvailableException
 */
final class ResolutionFailedException extends CascadeException
{
    // This class is deprecated and kept only for backward compatibility.
    // All factory methods have been moved to specific exception classes.
}
