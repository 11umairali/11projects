<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->randomElement(['Sales Pipeline', 'Enterprise Deals', 'Inbound Leads']),
            'is_default' => true,
        ];
    }
}
