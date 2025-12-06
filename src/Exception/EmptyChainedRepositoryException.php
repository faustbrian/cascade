<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when ChainedRepository is instantiated with no repositories.
 * @author Brian Faust <brian@cline.sh>
 */
final class EmptyChainedRepositoryException extends CascadeException
{
    public static function create(): self
    {
        return new self('ChainedRepository requires at least one repository');
    }
}
