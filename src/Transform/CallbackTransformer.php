<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Transform;

use Cline\Cascade\Source\SourceInterface;

/**
 * Transformer implementation that delegates to a callable.
 *
 * Provides a lightweight way to create transformers from closures or
 * callable objects without defining a full class. Useful for inline
 * transformations and quick prototyping.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CallbackTransformer implements TransformerInterface
{
    /**
     * Create a new callback-based transformer.
     *
     * @param callable(mixed, SourceInterface): mixed $callback Transformation function that receives
     *                                                          the resolved value and source, returning
     *                                                          the transformed result. Should be a pure
     *                                                          function for predictable behavior.
     */
    public function __construct(
        private mixed $callback,
    ) {}

    /**
     * Transform the resolved value using the wrapped callback.
     *
     * Invokes the callback function with the value and source, returning
     * the transformed result. Any exceptions thrown by the callback will
     * propagate to the caller.
     *
     * @param  mixed           $value  The resolved value to transform
     * @param  SourceInterface $source The source that provided the value
     * @return mixed           The transformed value
     */
    public function transform(mixed $value, SourceInterface $source): mixed
    {
        return ($this->callback)($value, $source);
    }
}
