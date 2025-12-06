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
 * Interface for value transformers.
 *
 * Transformers modify resolved values before they are returned to the caller.
 */
interface TransformerInterface
{
    /**
     * Transform the resolved value.
     *
     * @param mixed $value The resolved value
     * @param SourceInterface $source The source that provided the value
     * @return mixed The transformed value
     */
    public function transform(mixed $value, SourceInterface $source): mixed;
}
