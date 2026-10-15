<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowNode>
 */
class WorkflowNodeFactory extends Factory
{
    protected $model = WorkflowNode::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'type' => 'action',
            'subtype' => 'send_email',
            'config' => [],
            'position_x' => fake()->randomFloat(1, 0, 800),
            'position_y' => fake()->randomFloat(1, 0, 600),
        ];
    }

    public function trigger(string $subtype = 'email_received'): static
    {
        return $this->state(fn () => [
            'type' => 'trigger',
            'subtype' => $subtype,
        ]);
    }

    public function condition(string $subtype = 'if_else'): static
    {
        return $this->state(fn () => [
            'type' => 'condition',
            'subtype' => $subtype,
        ]);
    }

    public function action(string $subtype = 'send_email'): static
    {
        return $this->state(fn () => [
            'type' => 'action',
            'subtype' => $subtype,
        ]);
    }
}
