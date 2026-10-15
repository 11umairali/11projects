<?php

use App\Models\User;
use App\Models\Message;
use App\Models\EmailAccount;
use App\Services\AdminImpersonation;
use App\Services\Email\EmailSendService;
use App\Mail\DynamicEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, status TEXT, is_admin INTEGER, remember_token TEXT, email_verified_at TEXT)');
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'is_admin' => 1, 'status' => 'active'],
        ['id' => 2, 'name' => 'User', 'email' => 'user@example.test', 'is_admin' => 0, 'status' => 'active'],
    ]);
});

test('return to admin restores the original web identity and clears impersonation', function () {
    $request = Request::create('/admin/impersonation/stop', 'POST', ['admin_id' => 2]);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('admin_impersonating', 1);
    Auth::guard('web')->login(User::find(2));
    expect(app(AdminImpersonation::class)->restore($request))->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe(1)
        ->and($request->session()->has('admin_impersonating'))->toBeFalse();
});

test('return rejects a suspended original admin and does not trust request identity', function () {
    DB::table('users')->where('id', 1)->update(['status' => 'suspended']);
    $request = Request::create('/admin/impersonation/stop', 'POST', ['admin_id' => 1]);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('admin_impersonating', 1);
    Auth::guard('web')->login(User::find(2));
    expect(app(AdminImpersonation::class)->restore($request))->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

test('verified mailbox accounts never receive a verification notification', function () {
    Notification::fake();
    $user = User::find(2);
    $user->forceFill(['email_verified_at' => now()]);
    $user->sendEmailVerificationNotification();
    Notification::assertNothingSent();
});

test('mail threading retains both parent and references and includes plain text', function () {
    $mail = new DynamicEmail(['to@example.test'], [], [], 'A subject', '<p>Hello</p><p>World</p>', inReplyTo: '<parent@example.test>', referencesHeader: ['<root@example.test>']);
    expect($mail->headers()->text)->toBe(['In-Reply-To' => '<parent@example.test>'])
        ->and($mail->headers()->references)->toBe(['root@example.test'])
        ->and($mail->plainTextBody())->toBe("Hello\n\nWorld");
});

test('sending the same message twice delivers once and deduplicates recipients', function () {
    DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, workspace_id INTEGER, sent_at TEXT, scheduled_at TEXT, schedule_status TEXT, delivery_status TEXT, delivery_error TEXT, message_id_header TEXT, to_emails TEXT, cc_emails TEXT, bcc_emails TEXT, body_html TEXT, updated_at TEXT, deleted_at TEXT)');
    // Older compose paths set sent_at before transport. It must not suppress the first delivery.
    DB::table('messages')->insert(['id' => 1, 'workspace_id' => 9, 'delivery_status' => 'pending', 'sent_at' => now(), 'to_emails' => '["one@example.test"]', 'cc_emails' => '["ONE@example.test", "two@example.test"]', 'bcc_emails' => '["two@example.test"]']);
    $service = new class extends EmailSendService {
        public int $deliveries = 0;
        protected function shouldTrackOneToOne(): bool { return false; }
        protected function sendViaSMTP(Message $message, EmailAccount $account, string $html): bool {
            $this->deliveries++;
            expect($message->message_id_header)->not->toBeEmpty();
            expect($message->fresh()->message_id_header)->toBe($message->message_id_header);
            expect($message->cc_emails)->toBe(['two@example.test'])->and($message->bcc_emails)->toBe([]);
            return true;
        }
    };
    $account = new EmailAccount(['provider' => 'custom']);
    Message::withoutEvents(function () use ($service, $account) {
        expect($service->sendReply(Message::find(1), $account))->toBeTrue();
        expect($service->sendReply(Message::find(1), $account))->toBeTrue();
    });
    expect($service->deliveries)->toBe(1);
});

test('Gmail MIME contains UTF-8 subject plain text HTML Bcc and threading', function () {
    $service = new class extends EmailSendService {
        protected function getSignature(EmailAccount $account, ?Message $message = null): ?string { return null; }
        protected function getAttachmentPaths(Message $message): array { return []; }
        public function raw(Message $message, EmailAccount $account): string { return $this->buildRawRfc2822($message, $account, '<p>Hello world</p>'); }
    };
    $message = new Message(['subject' => 'Hello مرحبا', 'to_emails' => ['to@example.test'], 'bcc_emails' => ['hidden@example.test'], 'in_reply_to' => '<parent@example.test>', 'references_header' => ['<root@example.test>']]);
    $raw = $service->raw($message, new EmailAccount(['email' => 'from@example.test', 'display_name' => 'Sender']));
    expect($raw)->toContain('text/plain', 'text/html', 'multipart/alternative', 'Bcc: hidden@example.test', 'In-Reply-To: <parent@example.test>', 'References: <root@example.test>');
});

test('return route is accessible to the impersonated user outside admin middleware', function () {
    $this->withoutExceptionHandling()->withoutVite();
    // Localization is unrelated to this auth route; its catalog is not in the minimal fixture.
    $this->withoutMiddleware(\App\Http\Middleware\SetLocale::class);
    $this->actingAs(User::find(2))->withSession(['admin_impersonating' => 1])
        ->post('/admin/impersonation/stop')->assertRedirect(route('admin.dashboard'));
    expect(Auth::guard('web')->id())->toBe(1);
});

test('newly provisioned mailbox persists verification before the registration event', function () {
    DB::statement('DROP TABLE users');
    // SQLite json_valid(NULL) differs from the production nullable JSON column.
    DB::unprepared(str_replace('CHECK (json_valid(`notification_preferences`))', 'CHECK (`notification_preferences` IS NULL OR json_valid(`notification_preferences`))', file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql'))));
    $mailbox = Mockery::mock(\App\Services\CyberPanelMailbox::class);
    $mailbox->shouldReceive('create')->once()->with('newuser', 'synthetic-password');
    $mailbox->shouldNotReceive('delete');
    $mailbox->shouldReceive('address')->once()->with('newuser')->andReturn('newuser@example.test');
    $recovery = Mockery::mock(\App\Services\RecoveryPhrase::class);
    $recovery->shouldReceive('generate')->once()->andReturn(['synthetic']);
    $recovery->shouldReceive('hash')->once()->andReturn('synthetic-hash');
    [$user] = (new \App\Services\AccountCreator($mailbox, $recovery))->create('New user', 'newuser', 'synthetic-password', '127.0.0.1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::fake();
    event(new \Illuminate\Auth\Events\Registered($user));
    Notification::assertNothingSent();
});


test('SMTP uses the persisted mail identity so the sent copy is not imported twice', function () {
    $mail = new DynamicEmail(['to@example.test'], [], [], 'A subject', '<p>Hello</p>', messageId: '<persisted@example.test>');
    expect($mail->headers()->messageId)->toBe('persisted@example.test');
});


test('Gmail MIME preserves the stored Message-ID on provider submission', function () {
    $service = new class extends EmailSendService {
        protected function getSignature(EmailAccount $account, ?Message $message = null): ?string { return null; }
        protected function getAttachmentPaths(Message $message): array { return []; }
        public function raw(Message $message, EmailAccount $account): string { return $this->buildRawRfc2822($message, $account, '<p>Hello</p>'); }
    };
    $message = new Message(['subject' => 'Hello', 'to_emails' => ['to@example.test'], 'from_email' => 'from@example.test', 'message_id_header' => '<persisted@example.test>']);
    expect($service->raw($message, new EmailAccount(['email' => 'from@example.test'])))->toContain('Message-ID: <persisted@example.test>');
});
