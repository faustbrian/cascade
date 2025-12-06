<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when symfony/yaml package is required but not installed.
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlPackageRequiredException extends CascadeException
{
    public static function create(): self
    {
        return new self('YamlRepository requires symfony/yaml package. Install it with: composer require symfony/yaml');
    }
}
