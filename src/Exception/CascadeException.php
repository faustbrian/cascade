<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

use RuntimeException;

/**
 * Base exception for all Cascade-related exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 * @infection-ignore-all
 */
abstract class CascadeException extends RuntimeException {}
