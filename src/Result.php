<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade;

use Cline\Cascade\Source\SourceInterface;

/**
 * Represents the result of a cascade resolution operation.
 *
 * Immutable value object containing the resolved value, source information, and metadata
 * about the resolution process. Provides complete visibility into which sources were
 * attempted and which source (if any) successfully provided a value.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Result
{
    /**
     * Create a new resolution result.
     *
     * @param mixed                $value            The resolved value, or null if no source provided a value
     * @param bool                 $found            Whether a value was successfully resolved from a source
     * @param null|SourceInterface $source           The source that provided the value, or null if not found
     * @param array<int, string>   $attemptedSources Ordered list of source names that were queried during resolution.
     *                                               Useful for debugging and understanding the cascade flow.
     * @param array<string, mixed> $metadata         Additional source-specific metadata about the resolution.
     *                                               May include cache hits, query times, or other debugging info.
     */
    public function __construct(
        private mixed $value,
        private bool $found,
        private ?SourceInterface $source,
        private array $attemptedSources,
        private array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * Factory method for creating a result when a source successfully provided a value.
     * Marks the result as found and captures the source, value, and metadata.
     *
     * @param  mixed                $value     The resolved value from the source
     * @param  SourceInterface      $source    The source that provided the value
     * @param  array<int, string>   $attempted Ordered list of source names attempted before success
     * @param  array<string, mixed> $metadata  Additional source-specific metadata
     * @return self                 Successful resolution result
     */
    public static function found(
        mixed $value,
        SourceInterface $source,
        array $attempted,
        array $metadata = [],
    ): self {
        return new self(
            value: $value,
            found: true,
            source: $source,
            attemptedSources: $attempted,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     *
     * Factory method for creating a result when no source provided a value.
     * Marks the result as not found with null value and source.
     *
     * @param  array<int, string> $attempted Ordered list of source names that were queried
     * @return self               Failed resolution result
     */
    public static function notFound(array $attempted): self
    {
        return new self(
            value: null,
            found: false,
            source: null,
            attemptedSources: $attempted,
            metadata: [],
        );
    }

    /**
     * Get the resolved value.
     *
     * @return mixed The resolved value if found, or null if not found
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Check if a value was found.
     *
     * @return bool True if a source provided a value, false if all sources failed
     */
    public function wasFound(): bool
    {
        return $this->found;
    }

    /**
     * Get the source that provided the value.
     *
     * @return null|SourceInterface The source instance that provided the value, or null if not found
     */
    public function getSource(): ?SourceInterface
    {
        return $this->source;
    }

    /**
     * Get the name of the source that provided the value.
     *
     * @return null|string The source name if found, or null if not found
     */
    public function getSourceName(): ?string
    {
        return $this->source?->getName();
    }

    /**
     * Get the list of sources that were attempted during resolution.
     *
     * Returns source names in the order they were queried. Useful for debugging
     * to understand which sources were tried and in what order.
     *
     * @return array<int, string> Ordered list of source names attempted during resolution
     */
    public function getAttemptedSources(): array
    {
        return $this->attemptedSources;
    }

    /**
     * Get additional metadata from the resolution.
     *
     * Metadata is provided by the source that successfully resolved the value.
     * May include information like cache hits, query times, or other source-specific
     * debugging information.
     *
     * @return array<string, mixed> Source-specific metadata, or empty array if not found
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
