<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Base exception for all repository-related errors in the Cascade system.
 *
 * This exception serves as a marker for errors originating from repository
 * operations, including chained repositories and repository management. While
 * currently used as a base class without specific factory methods, it provides
 * a distinct exception type for repository-level failures separate from other
 * Cascade exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RepositoryException extends CascadeException {}
