<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowEdge>
 */
class WorkflowEdgeFactory extends Factory
{
    protected $model = WorkflowEdge::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'from_node_id' => WorkflowNode::factory(),
            'to_node_id' => WorkflowNode::factory(),
            'label' => null,
        ];
    }

    public function yes(): static
    {
        return $this->state(fn () => ['label' => 'yes']);
    }

    public function no(): static
    {
        return $this->state(fn () => ['label' => 'no']);
    }
}
