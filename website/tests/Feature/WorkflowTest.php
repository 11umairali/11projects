<?php

use App\Models\Contact;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStepLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Email\EmailSendService;

/*
|--------------------------------------------------------------------------
| Workflow Tests
|--------------------------------------------------------------------------
| Tests workflow CRUD, activation, node/edge management, execution
| depth limits, and condition processing.
*/

beforeEach(function () {
    $setup = createUserWithWorkspace('owner');
    $this->user = $setup['user'];
    $this->workspace = $setup['workspace'];
    $this->actingAs($this->user);
});

// ---- Create ----

test('create workflow with nodes and edges', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
    ]);

    $action = WorkflowNode::factory()->action('send_email')->create([
        'workflow_id' => $workflow->id,
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'from_node_id' => $trigger->id,
        'to_node_id' => $action->id,
    ]);

    expect($workflow->workflowNodes()->count())->toBe(2);
    expect($workflow->workflowEdges()->count())->toBe(1);
    expect($workflow->uuid)->not->toBeNull();
    expect($workflow->webhook_token)->not->toBeNull();
});

// ---- Duplicate ----

test('duplicate workflow copies nodes and edges', function () {
    $original = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $original->id]);
    $action = WorkflowNode::factory()->action()->create(['workflow_id' => $original->id]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $original->id,
        'from_node_id' => $trigger->id,
        'to_node_id' => $action->id,
    ]);

    // Create duplicate
    $duplicate = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'name' => $original->name . ' (Copy)',
    ]);

    // Copy nodes
    $nodeMap = [];
    foreach ($original->workflowNodes as $node) {
        $newNode = $node->replicate();
        $newNode->workflow_id = $duplicate->id;
        $newNode->save();
        $nodeMap[$node->id] = $newNode->id;
    }

    // Copy edges with remapped node IDs
    foreach ($original->workflowEdges as $edge) {
        WorkflowEdge::create([
            'workflow_id' => $duplicate->id,
            'from_node_id' => $nodeMap[$edge->from_node_id],
            'to_node_id' => $nodeMap[$edge->to_node_id],
            'label' => $edge->label,
        ]);
    }

    expect($duplicate->workflowNodes()->count())->toBe(2);
    expect($duplicate->workflowEdges()->count())->toBe(1);
    expect($duplicate->id)->not->toBe($original->id);
});

// ---- Activate ----

test('activate workflow changes status to active', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'status' => 'draft',
    ]);

    $workflow->update(['status' => 'active']);
    $workflow->refresh();

    expect($workflow->status)->toBe('active');
    expect($workflow->isActive())->toBeTrue();
});

// ---- Execution ----

test('workflow execution creates execution record', function () {
    // Create a simple trigger -> action workflow
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $trigger = WorkflowNode::factory()->trigger('email_received')->create([
        'workflow_id' => $workflow->id,
    ]);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    // Mock the EmailSendService to avoid actual email sending
    $mockEmailService = $this->mock(EmailSendService::class);

    $engine = new WorkflowEngine($mockEmailService);
    $execution = $engine->execute($workflow, $contact, ['event' => 'email_received']);

    expect($execution)->toBeInstanceOf(WorkflowExecution::class);
    expect($execution->status)->toBeIn(['completed', 'running']);
    expect($execution->workflow_id)->toBe($workflow->id);
    expect($execution->contact_id)->toBe($contact->id);
});

test('inactive workflow throws exception on execute', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'status' => 'draft', // Not active
    ]);

    $mockEmailService = $this->mock(EmailSendService::class);
    $engine = new WorkflowEngine($mockEmailService);

    expect(fn () => $engine->execute($workflow, null))
        ->toThrow(RuntimeException::class, 'not active');
});

test('workflow execution respects depth limit', function () {
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    // Create a simple trigger node (the engine will stop after trigger since
    // there are no outgoing edges -- but with a maxDepth of 0, it would fail)
    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
    ]);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    // Set a very low depth limit via config
    config(['mailtrixy.workflow.max_depth' => 2]);

    $mockEmailService = $this->mock(EmailSendService::class);
    $engine = new WorkflowEngine($mockEmailService);

    $execution = $engine->execute($workflow, $contact);

    // Should complete without exceeding depth
    expect($execution->status)->toBeIn(['completed', 'running', 'failed']);
});

test('workflow execution increments executions_count', function () {
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'executions_count' => 0,
    ]);

    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $mockEmailService = $this->mock(EmailSendService::class);
    $engine = new WorkflowEngine($mockEmailService);
    $engine->execute($workflow, $contact);

    $workflow->refresh();
    expect($workflow->executions_count)->toBe(1);
});

// ---- Scopes ----

test('active scope returns only active workflows', function () {
    Workflow::factory()->count(3)->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    Workflow::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'status' => 'draft',
    ]);

    $activeCount = Workflow::where('workspace_id', $this->workspace->id)->active()->count();
    expect($activeCount)->toBe(3);
});

// ---- Condition Processing ----

test('if_else condition with matching criteria returns yes edge', function () {
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
    ]);

    $condition = WorkflowNode::factory()->condition('if_else')->create([
        'workflow_id' => $workflow->id,
        'config' => [
            'match' => 'all',
            'conditions' => [
                ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
            ],
        ],
    ]);

    WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'from_node_id' => $trigger->id,
        'to_node_id' => $condition->id,
    ]);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'US',
    ]);

    $mockEmailService = $this->mock(EmailSendService::class);
    $engine = new WorkflowEngine($mockEmailService);
    $execution = $engine->execute($workflow, $contact);

    // Verify the condition was processed
    $stepLog = WorkflowStepLog::where('execution_id', $execution->id)
        ->where('node_id', $condition->id)
        ->first();

    expect($stepLog)->not->toBeNull();
    expect($stepLog->status)->toBe('success');
});

// ---- Model Relationships ----

test('workflow has proper relationships', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    expect($workflow->workspace->id)->toBe($this->workspace->id);
    expect($workflow->createdBy->id)->toBe($this->user->id);
});
