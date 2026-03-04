<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Base exception for all source-related errors in the Cascade configuration system.
 *
 * This abstract exception serves as a marker interface for errors that occur when
 * loading or parsing configuration sources (YAML files, JSON files, etc.). All
 * source-specific exceptions should extend this class to enable targeted error
 * handling and categorization of configuration loading failures.
 *
 * @author Brian Faust <brian@cline.sh>
 * @infection-ignore-all
 */
abstract class SourceException extends CascadeException {}
