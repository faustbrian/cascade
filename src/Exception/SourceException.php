<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Base exception for all source-related errors.
 *
 * @author Brian Faust <brian@cline.sh>
 * @infection-ignore-all
 */
abstract class SourceException extends CascadeException {}
