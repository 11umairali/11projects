<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'contact_id' => Contact::factory(),
            'pipeline_id' => Pipeline::factory(),
            'deal_stage_id' => DealStage::factory(),
            'title' => fake()->company() . ' - ' . fake()->bs(),
            'value' => fake()->randomFloat(2, 500, 50000),
            'currency' => 'usd',
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'status' => 'open',
        ];
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'status' => 'won',
            'won_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'status' => 'lost',
            'lost_at' => now(),
            'lost_reason' => 'Budget constraints',
        ]);
    }
}
