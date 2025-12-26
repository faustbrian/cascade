<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Database\Factories;

use Cline\Cascade\Database\Models\Resolver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Resolver model instances for testing.
 *
 * @extends Factory<Resolver>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolverFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Resolver>
     */
    protected $model = Resolver::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word().'.resolver',
            'description' => $this->faker->sentence(),
            'definition' => [
                'type' => $this->faker->randomElement(['string', 'integer', 'boolean', 'array']),
                'default' => $this->faker->word(),
            ],
            'metadata' => [
                'version' => '1.0.0',
                'created_by' => $this->faker->name(),
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the resolver is inactive.
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the resolver is active.
     *
     * @return static
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Set a specific name for the resolver.
     *
     * @param  string $name The resolver name
     * @return static
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
        ]);
    }

    /**
     * Set a specific definition for the resolver.
     *
     * @param  array<string, mixed> $definition The resolver definition
     * @return static
     */
    public function withDefinition(array $definition): static
    {
        return $this->state(fn (array $attributes): array => [
            'definition' => $definition,
        ]);
    }

    /**
     * Create a resolver without metadata.
     *
     * @return static
     */
    public function withoutMetadata(): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => null,
        ]);
    }

    /**
     * Create a resolver without description.
     *
     * @return static
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes): array => [
            'description' => null,
        ]);
    }
}
