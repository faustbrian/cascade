<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade;

use Cline\Cascade\Exception\ResolutionFailedForKeyException;
use Cline\Cascade\Source\ArraySource;
use Cline\Cascade\Source\CallbackSource;
use Cline\Cascade\Source\SourceInterface;

use function array_map;
use function is_callable;
use function usort;

/**
 * Resolver manages a named collection of sources and resolution logic.
 *
 * Resolvers execute the cascade resolution algorithm by trying each
 * source in priority order until a value is found or all sources fail.
 * @author Brian Faust <brian@cline.sh>
 */
final class Resolver
{
    /** @var array<int, array{source: SourceInterface, priority: int}> */
    private array $sources = [];

    /** @var array<callable> */
    private array $transformers = [];

    private bool $sourcesSorted = false;

    /**
     * @param string $name Unique name for this resolver
     */
    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Add a source with optional name and priority.
     *
     * Lower priority values are queried first (1 before 10).
     */
    public function source(
        string $name,
        SourceInterface $source,
        int $priority = 0,
    ): self {
        $this->sources[] = [
            'source' => $source,
            'priority' => $priority,
        ];

        $this->sourcesSorted = false;

        return $this;
    }

    /**
     * Add a callback source.
     */
    public function fromCallback(
        string $name,
        callable $resolver,
        ?callable $supports = null,
        int $priority = 0,
    ): self {
        return $this->source(
            name: $name,
            source: new CallbackSource(
                name: $name,
                resolver: $resolver,
                supports: $supports,
            ),
            priority: $priority,
        );
    }

    /**
     * Add static values as a source.
     *
     * @param array<string, mixed> $values
     */
    public function fromArray(
        string $name,
        array $values,
        int $priority = 0,
    ): self {
        return $this->source(
            name: $name,
            source: new ArraySource(
                name: $name,
                values: $values,
            ),
            priority: $priority,
        );
    }

    /**
     * Add a transformer.
     */
    public function transform(callable $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    /**
     * Resolve a value with default fallback.
     *
     * @param array<string, mixed> $context
     */
    public function get(
        string $key,
        array $context = [],
        mixed $default = null,
    ): mixed {
        $result = $this->resolve($key, $context);

        if ($result->wasFound()) {
            return $result->getValue();
        }

        if (is_callable($default)) {
            return $default();
        }

        return $default;
    }

    /**
     * Resolve with full result metadata.
     *
     * @param array<string, mixed> $context
     */
    public function resolve(string $key, array $context = []): Result
    {
        $this->ensureSourcesSorted();

        $attempted = [];

        foreach ($this->sources as $entry) {
            $source = $entry['source'];

            if (!$source->supports($key, $context)) {
                continue;
            }

            $attempted[] = $source->getName();

            $value = $source->get($key, $context);

            if ($value !== null) {
                // Apply transformers
                foreach ($this->transformers as $transformer) {
                    $value = $transformer($value, $source);
                }

                return Result::found(
                    value: $value,
                    source: $source,
                    attempted: $attempted,
                    metadata: $source->getMetadata(),
                );
            }
        }

        return Result::notFound($attempted);
    }

    /**
     * Resolve or throw if not found.
     *
     * @param array<string, mixed> $context
     *
     * @throws ResolutionFailedForKeyException
     */
    public function getOrFail(string $key, array $context = []): mixed
    {
        $result = $this->resolve($key, $context);

        if (!$result->wasFound()) {
            throw ResolutionFailedForKeyException::withAttemptedSources($key, $result->getAttemptedSources());
        }

        return $result->getValue();
    }

    /**
     * Get the name of this resolver.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get ordered sources.
     *
     * @return array<SourceInterface>
     */
    public function getSources(): array
    {
        $this->ensureSourcesSorted();

        return array_map(
            static fn (array $entry): SourceInterface => $entry['source'],
            $this->sources,
        );
    }

    /**
     * Ensure sources are sorted by priority.
     */
    private function ensureSourcesSorted(): void
    {
        if ($this->sourcesSorted) {
            return;
        }

        usort(
            $this->sources,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority'],
        );

        $this->sourcesSorted = true;
    }
}
