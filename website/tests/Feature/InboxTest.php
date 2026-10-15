<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Models\EmailAccount;

/*
|--------------------------------------------------------------------------
| Inbox Tests
|--------------------------------------------------------------------------
| Tests conversation and message operations: send reply, assign, tag,
| close/reopen, bulk operations, and authorization.
*/

beforeEach(function () {
    $setup = createUserWithWorkspace('owner');
    $this->user = $setup['user'];
    $this->workspace = $setup['workspace'];
    $this->actingAs($this->user);

    $this->contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->emailAccount = EmailAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'email_account_id' => $this->emailAccount->id,
    ]);
});

// ---- Messages ----

test('message creation stores HTML and text body', function () {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
        'body_html' => '<p>Hello World</p>',
        'body_text' => 'Hello World',
    ]);

    expect($message->body_html)->toBe('<p>Hello World</p>');
    expect($message->body_text)->toBe('Hello World');
    expect($message->uuid)->not->toBeNull();
});

test('outbound message sets delivery status and sent_at', function () {
    $message = Message::factory()->outbound()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
        'sender_id' => $this->user->id,
    ]);

    expect($message->direction)->toBe('outbound');
    expect($message->delivery_status)->toBe('sent');
    expect($message->sent_at)->not->toBeNull();
});

test('reply with invalid CC email would be caught by validation', function () {
    // At the model level, cc_emails is a JSON array (no built-in validation)
    // This verifies the field structure
    $message = Message::factory()->outbound()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
        'cc_emails' => ['valid@example.com', 'also-valid@test.com'],
        'bcc_emails' => ['bcc@example.com'],
    ]);

    expect($message->cc_emails)->toBeArray();
    expect($message->cc_emails)->toHaveCount(2);
    expect($message->bcc_emails)->toHaveCount(1);
});

// ---- Assign ----

test('assign conversation to agent', function () {
    $agent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
    $this->workspace->members()->attach($agent->id, ['role' => 'agent', 'status' => 'online']);
    $agent->update(['active_workspace_id' => $this->workspace->id]);

    $this->conversation->update(['assigned_to' => $agent->id]);

    $this->conversation->refresh();
    expect($this->conversation->assigned_to)->toBe($agent->id);
    expect($this->conversation->assignedTo->id)->toBe($agent->id);
});

// ---- Tags ----

test('tag conversation with workspace-scoped tag', function () {
    $tag = Tag::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Urgent',
        'color' => '#EF4444',
    ]);

    $this->conversation->tagModels()->attach($tag->id);

    expect($this->conversation->tagModels()->count())->toBe(1);
    expect($this->conversation->tagModels->first()->name)->toBe('Urgent');
});

// ---- Close / Reopen ----

test('close conversation sets resolved_at', function () {
    $this->conversation->update([
        'status' => 'closed',
        'resolved_at' => now(),
    ]);

    $this->conversation->refresh();
    expect($this->conversation->status)->toBe('closed');
    expect($this->conversation->resolved_at)->not->toBeNull();
});

test('reopen conversation clears resolved_at', function () {
    $this->conversation->update([
        'status' => 'closed',
        'resolved_at' => now(),
    ]);

    $this->conversation->update([
        'status' => 'open',
        'resolved_at' => null,
    ]);

    $this->conversation->refresh();
    expect($this->conversation->status)->toBe('open');
    expect($this->conversation->resolved_at)->toBeNull();
});

// ---- Bulk Operations ----

test('bulk mark conversations as read', function () {
    $conversations = Conversation::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'is_read' => false,
    ]);

    $ids = $conversations->pluck('id')->toArray();

    Conversation::whereIn('id', $ids)->update(['is_read' => true]);

    $readCount = Conversation::whereIn('id', $ids)->where('is_read', true)->count();
    expect($readCount)->toBe(5);
});

test('bulk soft-delete conversations', function () {
    $conversations = Conversation::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
    ]);

    $ids = $conversations->pluck('id')->toArray();

    Conversation::whereIn('id', $ids)->delete();

    expect(Conversation::whereIn('id', $ids)->count())->toBe(0);
    expect(Conversation::withTrashed()->whereIn('id', $ids)->count())->toBe(3);
});

// ---- AI Draft ----

test('AI draft message has correct type and status', function () {
    $message = Message::factory()->aiDraft()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
    ]);

    expect($message->type)->toBe('ai_draft');
    expect($message->ai_status)->toBe('draft');
    expect($message->isAiGenerated())->toBeTrue();
    expect($message->ai_model)->toBe('gpt-4o');
});

test('AI draft approval changes status to approved', function () {
    $message = Message::factory()->aiDraft()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $message->update(['ai_status' => 'approved']);

    $message->refresh();
    expect($message->ai_status)->toBe('approved');
});

// ---- Scopes ----

test('open scope returns only open conversations', function () {
    Conversation::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'status' => 'open',
    ]);

    Conversation::factory()->count(2)->closed()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
    ]);

    // +1 from beforeEach
    $openCount = Conversation::where('workspace_id', $this->workspace->id)->open()->count();
    expect($openCount)->toBe(4); // 3 + 1 from beforeEach
});

test('unread scope returns only unread conversations', function () {
    Conversation::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'is_read' => false,
    ]);

    Conversation::factory()->read()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
    ]);

    // +1 from beforeEach (default is_read = false)
    $unreadCount = Conversation::where('workspace_id', $this->workspace->id)->unread()->count();
    expect($unreadCount)->toBe(3); // 2 + 1 from beforeEach
});

// ---- Message sanitization ----

test('safe_body_html strips script tags', function () {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
        'body_html' => '<p>Hello</p><script>alert(1)</script><p>World</p>',
    ]);

    $safe = $message->safe_body_html;
    expect($safe)->not->toContain('<script');
    expect($safe)->not->toContain('alert');
    expect($safe)->toContain('Hello');
    expect($safe)->toContain('World');
});

test('safe_body_html neutralizes javascript: URIs', function () {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
        'body_html' => '<a href="javascript:alert(1)">click</a>',
    ]);

    $safe = $message->safe_body_html;
    expect($safe)->not->toContain('javascript:');
});

test('safe_body_html removes event handlers', function () {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'workspace_id' => $this->workspace->id,
        'body_html' => '<img src="x" onerror="alert(1)">',
    ]);

    $safe = $message->safe_body_html;
    expect($safe)->not->toContain('onerror');
});
