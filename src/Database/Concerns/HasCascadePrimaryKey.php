<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Database\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use function class_uses_recursive;
use function config;
use function in_array;

/**
 * Dynamically apply the configured primary key strategy to Eloquent models.
 *
 * Based on the cascade.primary_key_type configuration, this trait will:
 * - Use auto-increment IDs (default Laravel behavior)
 * - Use UUIDs via HasUuids trait
 * - Use ULIDs via HasUlids trait
 *
 * This allows all Cascade models to respect a single configuration setting
 * for primary key strategy across the entire package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasCascadePrimaryKey
{
    /**
     * Initialize the trait and apply the configured primary key strategy.
     *
     * This method is automatically called by Laravel when the model is booted.
     * It delegates to the appropriate trait based on the configuration.
     */
    public function initializeHasCascadePrimaryKey(): void
    {
        $keyType = config('cascade.primary_key_type', 'id');

        match ($keyType) {
            'uuid' => $this->initializeHasUuids(),
            'ulid' => $this->initializeHasUlids(),
            default => null,
        };
    }

    /**
     * Get the primary key type for the model.
     *
     * @return string The primary key column name
     */
    public function getKeyName(): string
    {
        return match (config('cascade.primary_key_type', 'id')) {
            'uuid', 'ulid' => 'id',
            default => parent::getKeyName(),
        };
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string The key type (int or string)
     */
    public function getKeyType(): string
    {
        return match (config('cascade.primary_key_type', 'id')) {
            'uuid', 'ulid' => 'string',
            default => parent::getKeyType(),
        };
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool Whether IDs auto-increment
     */
    public function getIncrementing(): bool
    {
        return match (config('cascade.primary_key_type', 'id')) {
            'uuid', 'ulid' => false,
            default => parent::getIncrementing(),
        };
    }

    /**
     * Bootstrap UUID trait if configured.
     */
    private function initializeHasUuids(): void
    {
        if (in_array(HasUuids::class, class_uses_recursive($this), true)) {
            return;
        }

        $traits = class_uses_recursive($this);
        $traits[] = HasUuids::class;
    }

    /**
     * Bootstrap ULID trait if configured.
     */
    private function initializeHasUlids(): void
    {
        if (in_array(HasUlids::class, class_uses_recursive($this), true)) {
            return;
        }

        $traits = class_uses_recursive($this);
        $traits[] = HasUlids::class;
    }
}
