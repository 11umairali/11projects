<?php
use App\Models\{User, AdminBroadcast};
use App\Jobs\DeliverAdminBroadcast;
use App\Notifications\AdminBroadcastNotification;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Notification, Queue};

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    (require database_path('migrations/2026_10_15_000003_create_admin_broadcasts.php'))->up();
    for ($id = 1; $id <= 3; $id++) DB::table('users')->insert(['id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => 'User '.$id, 'email' => $id.'@example.test', 'notification_preferences' => '{}', 'status' => $id === 3 ? 'suspended' : 'active', 'is_admin' => $id === 1, 'admin_role' => $id === 1 ? 'super_admin' : null]);
    $this->actingAs(User::find(1));
    $this->broadcast = AdminBroadcast::create(['created_by' => 1, 'category' => 'general', 'title' => 'Hi {{name}}', 'body' => 'Account {{email}} <script>alert(1)</script>', 'accent' => '#2563EB', 'push' => true, 'email' => true]);
    Notification::fake(); Queue::fake();
});

test('only active super admins may use every broadcast endpoint', function () {
    $request = Request::create('/admin/broadcasts');
    expect((new AdminMiddleware)->handle($request, fn () => response('ok'), 'super_admin')->getContent())->toBe('ok');
    foreach (['admin', 'support', null] as $role) {
        $u = User::find(1); $u->admin_role = $role; $this->actingAs($u);
        expect(fn () => (new AdminMiddleware)->handle($request, fn () => response('ok'), 'super_admin'))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    }
    foreach (['index','store','update','show','send','duplicate'] as $action) expect(app('router')->getRoutes()->getByName('admin.broadcasts.'.$action)->gatherMiddleware())->toContain('admin:super_admin');
});

test('audience snapshot excludes suspended users and a send cannot be repeated', function () {
    $controller = new BroadcastController;
    $controller->send(Request::create('/', 'POST', ['audience' => 'all']), $this->broadcast);
    expect(DB::table('admin_broadcast_deliveries')->pluck('user_id')->all())->toBe([1, 2]);
    Queue::assertPushed(DeliverAdminBroadcast::class, 2);
    expect(fn () => $controller->send(Request::create('/', 'POST', ['audience' => 'all']), $this->broadcast))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($this->broadcast->fresh()->audience_count)->toBe(2);
});

test('test delivery leaves the original template draft and targets only the actor', function () {
    (new BroadcastController)->send(Request::create('/', 'POST', ['audience' => 'test']), $this->broadcast);
    expect($this->broadcast->fresh()->status)->toBe('draft');
    expect(DB::table('admin_broadcast_deliveries')->pluck('user_id')->all())->toBe([1]);
    expect(DB::table('admin_broadcast_deliveries')->value('broadcast_id'))->not->toBe($this->broadcast->id);
});

test('delivery claims are idempotent and each channel respects preferences', function () {
    User::find(2)->update(['notification_preferences' => ['events' => ['announcement_general' => ['email' => false, 'inApp' => true]]]]);
    $id = DB::table('admin_broadcast_deliveries')->insertGetId(['broadcast_id' => $this->broadcast->id, 'user_id' => 2]);
    $job = new DeliverAdminBroadcast($id); $job->handle(); $job->handle();
    Notification::assertSentToTimes(User::find(2), AdminBroadcastNotification::class, 1);
    expect(DB::table('admin_broadcast_deliveries')->find($id)->push_status)->toBe('submitted');
    expect(DB::table('admin_broadcast_deliveries')->find($id)->email_status)->toBe('skipped');
    expect($this->broadcast->fresh()->status)->toBe('completed');
});

test('email templates escape content and personalize supported tokens only', function () {
    $html = view('emails.admin-broadcast', ['broadcast' => $this->broadcast, 'recipient' => User::find(2)])->render();
    expect($html)->toContain('Hi User 2', '2@example.test', '&lt;script&gt;')->not->toContain('<script>');
    $notification = new AdminBroadcastNotification($this->broadcast, 'database');
    expect($notification->toArray(User::find(2))['type'])->toBe('announcement_general');
});
