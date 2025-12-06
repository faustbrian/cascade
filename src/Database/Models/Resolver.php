<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cascade\Database\Models;

use Cline\Cascade\Database\Concerns\HasCascadePrimaryKey;
use Database\Factories\ResolverFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

use function config;
use function is_string;

/**
 * Eloquent model for storing resolver definitions in the database.
 *
 * Stores multi-source resolution configurations with their sources and metadata.
 * Each resolver contains a set of sources that determine fallback chains for
 * configuration or credential resolution.
 *
 * The model automatically serializes resolver definitions to JSON for storage
 * and deserializes them back when retrieved.
 *
 * @property Carbon                    $created_at
 * @property array<string, mixed>      $definition  Resolver definition as JSON
 * @property null|string               $description Resolver description
 * @property int|string                $id          Primary key (type depends on configuration)
 * @property bool                      $is_active   Whether this resolver is currently active
 * @property null|array<string, mixed> $metadata    Additional resolver metadata
 * @property string                    $name        Unique resolver name
 * @property Carbon                    $updated_at
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @use HasFactory<Factory<static>>
 */
final class Resolver extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasCascadePrimaryKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'definition',
        'metadata',
        'is_active',
    ];

    /**
     * Find a resolver by name.
     *
     * @param string $name The resolver name
     *
     * @return null|static The resolver model or null
     */
    public static function findByName(string $name): ?static
    {
        return self::query()->where('name', $name)->first();
    }

    /**
     * Get the table associated with the model.
     */
    #[Override()]
    public function getTable(): string
    {
        $configuredTable = config('cascade.tables.resolvers');

        if (is_string($configuredTable)) {
            return $configuredTable;
        }

        return parent::getTable() ?: 'resolvers';
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return ResolverFactory::new();
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include active resolvers.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    #[Scope()]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive resolvers.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    #[Scope()]
    protected function inactive($query)
    {
        return $query->where('is_active', false);
    }
}
