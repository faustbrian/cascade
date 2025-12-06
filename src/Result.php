<?php

declare(strict_types=1);

/**
 * Copyright (c) Cline <brian@cline.sh>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Cline\Cascade;

use Cline\Cascade\Source\SourceInterface;

/**
 * Represents the result of a cascade resolution operation.
 *
 * Contains the resolved value, source information, and metadata
 * about the resolution process.
 */
final readonly class Result
{
    /**
     * @param mixed $value The resolved value or null if not found
     * @param bool $found Whether a value was found
     * @param SourceInterface|null $source The source that provided the value
     * @param array<string> $attemptedSources List of sources that were queried
     * @param array<string, mixed> $metadata Additional metadata from the source
     */
    public function __construct(
        private mixed $value,
        private bool $found,
        private ?SourceInterface $source,
        private array $attemptedSources,
        private array $metadata = [],
    ) {}

    /**
     * Get the resolved value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Check if a value was found.
     */
    public function wasFound(): bool
    {
        return $this->found;
    }

    /**
     * Get the source that provided the value.
     */
    public function getSource(): ?SourceInterface
    {
        return $this->source;
    }

    /**
     * Get the name of the source that provided the value.
     */
    public function getSourceName(): ?string
    {
        return $this->source?->getName();
    }

    /**
     * Get the list of sources that were attempted during resolution.
     *
     * @return array<string>
     */
    public function getAttemptedSources(): array
    {
        return $this->attemptedSources;
    }

    /**
     * Get additional metadata from the resolution.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a successful result.
     *
     * @param mixed $value The resolved value
     * @param SourceInterface $source The source that provided the value
     * @param array<string> $attempted List of sources attempted
     * @param array<string, mixed> $metadata Additional metadata
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
     * @param array<string> $attempted List of sources attempted
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
}
