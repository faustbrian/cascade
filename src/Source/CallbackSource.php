<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

/**
 * Closure-based source for flexible resolution logic.
 *
 * Allows defining custom resolution, support checking, and transformation
 * logic using closures. This is the most flexible source type.
 */
final readonly class CallbackSource implements SourceInterface
{
    /**
     * @param string $name Unique identifier for this source
     * @param callable(string, array<string, mixed>): mixed $resolver Closure to resolve values
     * @param callable(string, array<string, mixed>): bool|null $supports Optional closure to check support
     * @param callable(mixed): mixed|null $transformer Optional closure to transform values
     */
    public function __construct(
        private string $name,
        private mixed $resolver,
        private mixed $supports = null,
        private mixed $transformer = null,
    ) {}

    /**
     * Get the unique name of this source.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this source supports the given key/context combination.
     *
     * If no supports callable was provided, returns true for all keys/contexts.
     */
    public function supports(string $key, array $context): bool
    {
        if ($this->supports === null) {
            return true;
        }

        return ($this->supports)($key, $context);
    }

    /**
     * Attempt to resolve a value for the given key and context.
     *
     * Calls the resolver closure and optionally transforms the result
     * using the transformer closure if provided.
     */
    public function get(string $key, array $context): mixed
    {
        $value = ($this->resolver)($key, $context);

        if ($value !== null && $this->transformer !== null) {
            return ($this->transformer)($value);
        }

        return $value;
    }

    /**
     * Get metadata about this source for debugging and logging.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'type' => 'callback',
            'has_supports' => $this->supports !== null,
            'has_transformer' => $this->transformer !== null,
        ];
    }
}
