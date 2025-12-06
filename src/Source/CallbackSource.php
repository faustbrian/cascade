<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Source;

/**
 * Closure-based source for flexible resolution logic.
 *
 * Allows defining custom resolution, support checking, and transformation logic using
 * closures. This is the most flexible source type, enabling inline definition of complex
 * resolution strategies without creating dedicated source classes. Ideal for prototyping,
 * one-off integrations, or when resolution logic is too specific for a reusable source class.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CallbackSource implements SourceInterface
{
    /**
     * Create a new callback-based source.
     *
     * @param string                                            $name        Unique identifier for this source. Used for
     *                                                                       debugging and identifying the source in results.
     * @param callable(string, array<string, mixed>): mixed     $resolver    Closure to resolve values. Receives the key and
     *                                                                       context, returns the resolved value or null.
     * @param null|callable(string, array<string, mixed>): bool $supports    Optional closure to check if this source supports
     *                                                                       a given key/context. If not provided, source
     *                                                                       supports all keys.
     * @param null|callable(mixed): mixed                       $transformer Optional closure to transform resolved values before
     *                                                                       returning them. Applied only to non-null values.
     */
    public function __construct(
        private string $name,
        private mixed $resolver,
        private mixed $supports = null,
        private mixed $transformer = null,
    ) {}

    /**
     * Get the unique name of this source.
     *
     * @return string The unique identifier for this source
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this source supports the given key/context combination.
     *
     * If a supports callable was provided at construction, delegates to it for conditional
     * support logic. Otherwise, returns true for all keys/contexts, meaning this source
     * will be queried during every resolution.
     *
     * @param  string               $key     The key being resolved
     * @param  array<string, mixed> $context Additional context for resolution
     * @return bool                 True if this source should be queried, false to skip
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
     * Invokes the resolver closure with the key and context. If a transformer was provided
     * and the resolver returns a non-null value, the transformer is applied to the value
     * before returning it. Null values are returned as-is without transformation.
     *
     * @param  string               $key     The key to resolve
     * @param  array<string, mixed> $context Additional context for resolution
     * @return mixed                The resolved value (optionally transformed), or null if not found
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
     * Includes information about which optional callbacks (supports, transformer) are configured.
     * Useful for understanding the source's behavior and capabilities.
     *
     * @return array<string, mixed> Metadata including name, type, and configured callbacks
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
