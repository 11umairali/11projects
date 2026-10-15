<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Free', 'Starter', 'Pro', 'Enterprise']);

        return [
            'name' => $name,
            'slug' => strtolower($name) . '-' . fake()->unique()->randomNumber(4),
            'monthly_price' => fake()->randomFloat(2, 0, 199),
            'yearly_price' => fake()->randomFloat(2, 0, 1990),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Free',
            'slug' => 'free-' . fake()->unique()->randomNumber(4),
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'name' => 'Pro',
            'slug' => 'pro-' . fake()->unique()->randomNumber(4),
            'monthly_price' => 79,
            'yearly_price' => 790,
            'is_popular' => true,
        ]);
    }
}
