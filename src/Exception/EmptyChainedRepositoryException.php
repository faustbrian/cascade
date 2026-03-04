<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when ChainedRepository is instantiated without any repositories.
 *
 * A ChainedRepository requires at least one repository to function properly. This
 * exception prevents invalid configurations where no repositories would be available
 * for resolution operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EmptyChainedRepositoryException extends CascadeException
{
    /**
     * Create exception for empty repository chain.
     *
     * @return self Exception instance with descriptive message
     */
    public static function create(): self
    {
        return new self('ChainedRepository requires at least one repository');
    }
}
