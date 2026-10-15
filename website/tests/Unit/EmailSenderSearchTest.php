<?php
use App\Http\Controllers\InboxApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:', 'activitylog.enabled'=>false]); DB::purge('sqlite');
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, active_workspace_id INTEGER)');
    DB::table('users')->insert([['id'=>1,'active_workspace_id'=>7], ['id'=>2,'active_workspace_id'=>7]]); $this->actingAs(User::find(1));
    DB::statement('CREATE TABLE contacts (id INTEGER PRIMARY KEY, workspace_id INTEGER, first_name TEXT, last_name TEXT, email TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE conversations (id INTEGER PRIMARY KEY, workspace_id INTEGER, contact_id INTEGER, assigned_to INTEGER, subject TEXT, preview TEXT, is_read INTEGER, is_starred INTEGER, messages_count INTEGER, last_message_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT, channel TEXT, status TEXT, priority TEXT)');
    DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, conversation_id INTEGER, workspace_id INTEGER, direction TEXT, from_name TEXT, from_email TEXT, created_at TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE tags (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, color TEXT)');
    DB::statement('CREATE TABLE conversation_tag (conversation_id INTEGER, tag_id INTEGER)');
    (require database_path('migrations/2026_10_15_000002_create_email_notification_mutes.php'))->up();
    foreach ([1=>7, 2=>8] as $id=>$workspace) {
        DB::table('conversations')->insert(['id'=>$id,'workspace_id'=>$workspace,'channel'=>'email','status'=>'open','is_read'=>0,'is_starred'=>0,'messages_count'=>1,'subject'=>'Project','preview'=>'Hello']);
        DB::table('messages')->insert(['id'=>$id,'conversation_id'=>$id,'workspace_id'=>$workspace,'direction'=>'inbound','from_name'=>'Alice Smith','from_email'=>'alice@example.test','created_at'=>now()]);
    }
});

test('sender name and email match without a contact and stay workspace scoped', function () {
    foreach (['Alice Smith', 'alice@example.test'] as $search) {
        $data=(new InboxApiController)->conversations(Request::create('/', 'GET', ['search'=>$search, 'channel'=>'email']))->getData(true)['data'];
        expect(array_column($data,'id'))->toBe([1]);
    }
});

test('email mute metadata belongs to the viewer and star can be toggled off', function () {
    $controller=new InboxApiController;
    DB::table('email_notification_mutes')->insert(['user_id'=>2,'conversation_id'=>1]);
    $list=fn () => $controller->conversations(Request::create('/'))->getData(true)['data'][0];
    expect($list()['is_muted'])->toBeFalse();
    $controller->bulk(Request::create('/','POST',['ids'=>[1],'action'=>'mute']));
    expect($list()['is_muted'])->toBeTrue();
    $controller->bulk(Request::create('/','POST',['ids'=>[1],'action'=>'star']));
    expect($list()['is_starred'])->toBeTrue();
    $controller->bulk(Request::create('/','POST',['ids'=>[1],'action'=>'unstar']));
    expect($list()['is_starred'])->toBeFalse();
});
