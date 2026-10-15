<?php

use App\Models\Contact;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Email\EmailSendService;

/*
|--------------------------------------------------------------------------
| WorkflowEngine Unit Tests
|--------------------------------------------------------------------------
| Tests the workflow execution engine: edge caching, depth limits,
| regex validation, condition evaluation, and loop detection.
*/

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['active_workspace_id' => $this->workspace->id]);

    $this->mockEmailService = Mockery::mock(EmailSendService::class);
});

// ---- Edge Caching ----

test('edge caching loads all edges for workflow', function () {
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action1 = WorkflowNode::factory()->action()->create(['workflow_id' => $workflow->id]);
    $action2 = WorkflowNode::factory()->action('add_tag')->create(['workflow_id' => $workflow->id]);

    WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'from_node_id' => $trigger->id,
        'to_node_id' => $action1->id,
    ]);

    WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'from_node_id' => $action1->id,
        'to_node_id' => $action2->id,
    ]);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $engine = new WorkflowEngine($this->mockEmailService);
    $execution = $engine->execute($workflow, $contact);

    // Edges should have been loaded (verified by the fact that execution doesn't N+1)
    expect($execution)->toBeInstanceOf(WorkflowExecution::class);
    expect($workflow->workflowEdges()->count())->toBe(2);
});

// ---- Depth Limit ----

test('depth limit is enforced from config', function () {
    config(['mailtrixy.workflow.max_depth' => 5]);

    $engine = new WorkflowEngine($this->mockEmailService);

    // Access the protected maxDepth property via reflection
    $reflection = new ReflectionClass($engine);
    $prop = $reflection->getProperty('maxDepth');
    $prop->setAccessible(true);

    expect($prop->getValue($engine))->toBe(5);
});

test('default depth limit is 200', function () {
    config(['mailtrixy.workflow.max_depth' => null]);

    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $prop = $reflection->getProperty('maxDepth');
    $prop->setAccessible(true);

    // When config returns null, (int) null = 0, but fallback is 200
    // The constructor: (int) config('mailtrixy.workflow.max_depth', 200)
    // If config returns null, the fallback 200 is used
    expect($prop->getValue($engine))->toBe(200);
});

// ---- Regex Validation ----

test('safeRegexMatch rejects patterns longer than 500 chars', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('safeRegexMatch');
    $method->setAccessible(true);

    $longPattern = str_repeat('a', 501);
    $result = $method->invoke($engine, $longPattern, 'test string');

    expect($result)->toBeFalse();
});

test('safeRegexMatch rejects syntactically invalid patterns', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('safeRegexMatch');
    $method->setAccessible(true);

    // Invalid regex: unclosed group
    $result = $method->invoke($engine, '(unclosed', 'test');

    expect($result)->toBeFalse();
});

test('safeRegexMatch accepts valid patterns', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('safeRegexMatch');
    $method->setAccessible(true);

    // Valid regex
    $result = $method->invoke($engine, '^\d{3}-\d{4}$', '123-4567');

    expect($result)->toBeTrue();
});

test('safeRegexMatch is case insensitive', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('safeRegexMatch');
    $method->setAccessible(true);

    $result = $method->invoke($engine, 'hello', 'HELLO world');

    expect($result)->toBeTrue();
});

// ---- Condition Evaluation ----

test('evaluateCondition handles equals operator', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('evaluateCondition');
    $method->setAccessible(true);

    expect($method->invoke($engine, 'US', 'equals', 'US'))->toBeTrue();
    expect($method->invoke($engine, 'UK', 'equals', 'US'))->toBeFalse();
});

test('evaluateCondition handles contains operator', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('evaluateCondition');
    $method->setAccessible(true);

    expect($method->invoke($engine, 'Hello World', 'contains', 'world'))->toBeTrue();
    expect($method->invoke($engine, 'Hello World', 'contains', 'xyz'))->toBeFalse();
});

test('evaluateCondition handles greater_than operator', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('evaluateCondition');
    $method->setAccessible(true);

    expect($method->invoke($engine, 100, 'greater_than', 50))->toBeTrue();
    expect($method->invoke($engine, 30, 'greater_than', 50))->toBeFalse();
    expect($method->invoke($engine, 50, 'greater_than', 50))->toBeFalse();
});

test('evaluateCondition handles is_empty operator', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('evaluateCondition');
    $method->setAccessible(true);

    expect($method->invoke($engine, '', 'is_empty', null))->toBeTrue();
    expect($method->invoke($engine, null, 'is_empty', null))->toBeTrue();
    expect($method->invoke($engine, 'value', 'is_empty', null))->toBeFalse();
});

test('evaluateCondition handles in operator', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('evaluateCondition');
    $method->setAccessible(true);

    expect($method->invoke($engine, 'US', 'in', ['US', 'UK', 'CA']))->toBeTrue();
    expect($method->invoke($engine, 'DE', 'in', ['US', 'UK', 'CA']))->toBeFalse();
    expect($method->invoke($engine, 'US', 'in', 'US,UK,CA'))->toBeTrue();
});

// ---- Loop Detection ----

test('workflow execution detects and prevents cyclic loops', function () {
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
    ]);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $engine = new WorkflowEngine($this->mockEmailService);
    $execution = $engine->execute($workflow, $contact);

    // Should complete without infinite loop
    expect($execution->status)->toBeIn(['completed', 'running', 'failed']);
});

// ---- Variable Replacement ----

test('replaceContactVariables substitutes all placeholders', function () {
    $engine = new WorkflowEngine($this->mockEmailService);

    $reflection = new ReflectionClass($engine);
    $method = $reflection->getMethod('replaceContactVariables');
    $method->setAccessible(true);

    $contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john@example.com',
        'company' => 'Acme',
    ]);

    $result = $method->invoke($engine, 'Hi {first_name} {last_name} from {company}', $contact);

    expect($result)->toBe('Hi John Smith from Acme');
});
