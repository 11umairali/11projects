<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AdminBroadcast, User};
use App\Jobs\DeliverAdminBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BroadcastController extends Controller {
    public function index(Request $request) {
        $users = User::where('status', 'active')->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('email', 'like', '%'.$request->string('q').'%')))->orderBy('name')->paginate(30);
        return view('admin.broadcasts', ['users' => $users, 'broadcasts' => AdminBroadcast::latest()->paginate(20, ['*'], 'draft_page'), 'audienceCount' => User::where('status', 'active')->count()]);
    }
    public function store(Request $request, ?AdminBroadcast $broadcast = null) {
        $data = $request->validate(['category' => 'required|in:general,system,marketing,advertising,misc', 'title' => 'required|string|max:160', 'body' => 'required|string|max:12000',
            'button_label' => 'nullable|string|max:60', 'action_url' => 'nullable|url:https|max:2048', 'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'push' => 'boolean', 'email' => 'boolean']);
        $data['push'] = $request->boolean('push'); $data['email'] = $request->boolean('email');
        abort_unless($data['push'] || $data['email'], 422, 'Select at least one channel.');
        $b = DB::transaction(function () use ($broadcast, $data) {
            if (!$broadcast) return AdminBroadcast::create($data + ['created_by' => auth()->id()]);
            $b = AdminBroadcast::whereKey($broadcast->id)->lockForUpdate()->firstOrFail();
            abort_unless($b->status === 'draft', 409); $b->update($data); return $b;
        });
        return redirect()->route('admin.broadcasts.show', $b);
    }
    public function show(AdminBroadcast $broadcast) {
        $deliveries = DB::table('admin_broadcast_deliveries')->where('broadcast_id', $broadcast->id)->get(['push_status', 'email_status']);
        return view('admin.broadcast-preview', ['broadcast' => $broadcast, 'recipient' => auth()->user(), 'deliveries' => $deliveries, 'users' => User::where('status', 'active')->when(request()->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.request('q').'%')->orWhere('email', 'like', '%'.request('q').'%')))->orderBy('name')->limit(500)->get(['id', 'name', 'email'])]);
    }
    public function send(Request $request, AdminBroadcast $broadcast) {
        $data = $request->validate(['audience' => 'required|in:all,selected,test', 'user_ids' => 'nullable|array|max:500', 'user_ids.*' => 'integer|exists:users,id']);
        abort_if($data['audience'] === 'selected' && empty($data['user_ids']), 422, 'Choose recipients.');
        if ($data['audience'] === 'test') { $broadcast = $broadcast->replicate(); $broadcast->status = 'draft'; $broadcast->audience_count = 0; $broadcast->save(); }
        $deliveryIds = DB::transaction(function () use ($broadcast, $data) {
            $b = AdminBroadcast::whereKey($broadcast->id)->lockForUpdate()->firstOrFail();
            abort_unless($b->status === 'draft', 409, 'This broadcast has already been queued. Duplicate it to send again.');
            $q = User::where('status', 'active');
            if ($data['audience'] === 'test') $q->whereKey(auth()->id());
            elseif ($data['audience'] === 'selected') $q->whereIn('id', $data['user_ids']);
            $count = 0;
            $q->select('id')->chunkById(500, function ($users) use ($b, &$count) {
                DB::table('admin_broadcast_deliveries')->insert($users->map(fn ($u) => ['broadcast_id' => $b->id, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()])->all());
                $count += $users->count();
            });
            $b->update(['status' => $count ? 'queued' : 'completed', 'audience_count' => $count]);
            return DB::table('admin_broadcast_deliveries')->where('broadcast_id', $b->id)->pluck('id');
        });
        foreach ($deliveryIds as $id) DeliverAdminBroadcast::dispatch($id);
        return redirect()->route('admin.broadcasts.show', $broadcast)->with('success', 'Broadcast queued. Push submitted means handed to the push service, not confirmed device delivery.');
    }
    public function duplicate(AdminBroadcast $broadcast) {
        $copy = $broadcast->replicate(); $copy->status = 'draft'; $copy->audience_count = 0; $copy->created_by = auth()->id(); $copy->save();
        return redirect()->route('admin.broadcasts.show', $copy);
    }
}
