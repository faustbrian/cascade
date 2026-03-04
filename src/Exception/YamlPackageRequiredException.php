<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Exception;

/**
 * Exception thrown when attempting to use YAML functionality without the required package.
 *
 * This exception is raised when YamlRepository or other YAML-dependent components
 * are used but the symfony/yaml package is not installed in the project. The
 * exception message includes installation instructions to help resolve the issue.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlPackageRequiredException extends CascadeException
{
    /**
     * Create exception for missing symfony/yaml dependency.
     *
     * @return self The configured exception instance with installation instructions
     */
    public static function create(): self
    {
        return new self('YamlRepository requires symfony/yaml package. Install it with: composer require symfony/yaml');
    }
}
