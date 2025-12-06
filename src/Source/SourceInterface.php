<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

/**
 * Interface for cascade resolution sources.
 *
 * Sources provide values during the resolution process and can
 * optionally support conditional resolution based on context.
 * @author Brian Faust <brian@cline.sh>
 */
interface SourceInterface
{
    /**
     * Get the unique name of this source.
     */
    public function getName(): string;

    /**
     * Check if this source supports the given key/context combination.
     *
     * Return false to skip this source entirely during resolution.
     *
     * @param array<string, mixed> $context
     */
    public function supports(string $key, array $context): bool;

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Return null if the value cannot be found.
     *
     * @param array<string, mixed> $context
     */
    public function get(string $key, array $context): mixed;

    /**
     * Get metadata about this source for debugging and logging.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;
}
