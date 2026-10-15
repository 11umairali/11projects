<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'industry' => fake()->randomElement(['saas', 'ecommerce', 'education', 'healthcare', 'finance']),
            'team_size' => fake()->randomElement(['1-5', '6-20', '21-50', '51-200']),
            'timezone' => fake()->timezone(),
            'onboarding_completed' => true,
            'onboarding_step' => 5,
        ];
    }

    public function onboarding(): static
    {
        return $this->state(fn () => [
            'onboarding_completed' => false,
            'onboarding_step' => 1,
        ]);
    }
}
