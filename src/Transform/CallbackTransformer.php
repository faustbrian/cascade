<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Transform;

use Cline\Cascade\Source\SourceInterface;

/**
 * Transformer that wraps a callable.
 */
final readonly class CallbackTransformer implements TransformerInterface
{
    /**
     * @param callable(mixed, SourceInterface): mixed $callback
     */
    public function __construct(
        private mixed $callback,
    ) {}

    /**
     * Transform the value using the callback.
     */
    public function transform(mixed $value, SourceInterface $source): mixed
    {
        return ($this->callback)($value, $source);
    }
}
