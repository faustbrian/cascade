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
 * Contract for value transformers in the cascade resolution process.
 *
 * Transformers modify resolved values before they are returned to the caller,
 * enabling type coercion, validation, normalization, and other post-resolution
 * processing. Implementations should be stateless and thread-safe.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface TransformerInterface
{
    /**
     * Transform the resolved value from a source.
     *
     * Receives the raw value from the resolution chain and applies any necessary
     * transformations such as type casting, validation, formatting, or enrichment.
     * The source parameter provides context about where the value originated.
     *
     * @param  mixed           $value  The resolved value to transform
     * @param  SourceInterface $source The source that provided the value, enabling
     *                                 source-specific transformation logic
     * @return mixed           The transformed value ready for consumption
     */
    public function transform(mixed $value, SourceInterface $source): mixed;
}
