<?php

namespace Database\Factories;

use App\Models\DealStage;
use App\Models\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealStage>
 */
class DealStageFactory extends Factory
{
    protected $model = DealStage::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'name' => fake()->randomElement(['Lead', 'Qualified', 'Proposal', 'Negotiation', 'Closed Won']),
            'color' => fake()->hexColor(),
            'win_probability' => fake()->numberBetween(0, 100),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
