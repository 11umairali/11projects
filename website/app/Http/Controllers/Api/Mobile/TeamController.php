<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Exceptions\PlanLimitReachedException;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Team members + invitations. Mirrors Livewire\Settings\TeamManager. */
class TeamController extends Controller
{
    use AuthorizesApiActions;

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $wid = $request->user()->active_workspace_id;
        $w = Workspace::findOrFail($wid);

        $members = $w->members()->get()->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'avatar_url' => $u->avatar_path ? asset('storage/' . $u->avatar_path) : null,
            'role'       => $u->pivot->role,
            'status'     => $u->pivot->status ?? null,
            'is_me'      => $u->id === $request->user()->id,
        ]);

        $invites = DB::table('workspace_invites')
            ->where('workspace_id', $wid)->where('status', 'pending')->where('expires_at', '>', now())
            ->orderByDesc('id')->get(['id', 'email', 'role', 'expires_at', 'created_at']);

        return response()->json(['data' => ['members' => $members, 'invites' => $invites, 'my_role' => $request->user()->workspaceRole($wid)]]);
    }

    public function invite(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['email' => 'required|email|max:255', 'role' => 'required|in:admin,agent,viewer']);
        $wid = $request->user()->active_workspace_id;
        $workspace = Workspace::findOrFail($wid);

        try {
            app(PlanLimitService::class)->assertCanCreate($workspace, 'team_members');
        } catch (PlanLimitReachedException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'PLAN_LIMIT'], 403);
        }

        $existing = User::where('email', $d['email'])->first();
        if ($existing && $workspace->members()->where('users.id', $existing->id)->exists()) {
            return response()->json(['message' => 'This user is already a workspace member.', 'errors' => ['email' => ['This user is already a workspace member.']]], 422);
        }
        $live = DB::table('workspace_invites')->where('workspace_id', $wid)->where('email', $d['email'])
            ->where('status', 'pending')->where('expires_at', '>', now())->exists();
        if ($live) {
            return response()->json(['message' => 'An invitation has already been sent to this email.', 'errors' => ['email' => ['An invitation has already been sent to this email.']]], 422);
        }
        DB::table('workspace_invites')->where('workspace_id', $wid)->where('email', $d['email'])
            ->where(fn ($q) => $q->where('status', '!=', 'pending')->orWhere('expires_at', '<=', now()))->delete();

        $token = Str::random(64);
        DB::table('workspace_invites')->insert([
            'workspace_id' => $wid, 'email' => $d['email'], 'role' => $d['role'], 'token' => $token,
            'invited_by' => $request->user()->id, 'status' => 'pending',
            'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->sendInviteMail($workspace, $d['email'], $token);

        return response()->json(['message' => "Invitation sent to {$d['email']}."], 201);
    }

    public function changeRole(Request $request, int $userId): JsonResponse
    {
        $wid = $request->user()->active_workspace_id;
        $d = $request->validate(['role' => 'required|in:admin,agent,viewer']);

        if ($userId === $request->user()->id) return response()->json(['message' => 'You cannot change your own role.'], 422);
        if ($request->user()->workspaceRole($wid) !== 'owner') return response()->json(['message' => 'Only workspace owners can change member roles.'], 403);

        $target = DB::table('workspace_members')->where('workspace_id', $wid)->where('user_id', $userId)->value('role');
        if (!$target) return response()->json(['message' => 'Member not found.'], 404);
        if ($target === 'owner') return response()->json(['message' => "Cannot change the workspace owner's role."], 422);

        DB::table('workspace_members')->where('workspace_id', $wid)->where('user_id', $userId)->update(['role' => $d['role']]);
        return response()->json(['message' => 'Member role updated.']);
    }

    public function remove(Request $request, int $userId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'dangerous')) return $deny;
        $wid = $request->user()->active_workspace_id;

        if ($userId === $request->user()->id) return response()->json(['message' => 'You cannot remove yourself. Transfer ownership first.'], 422);
        $target = DB::table('workspace_members')->where('workspace_id', $wid)->where('user_id', $userId)->value('role');
        if (!$target) return response()->json(['message' => 'Member not found.'], 404);
        if ($target === 'owner') return response()->json(['message' => 'Cannot remove the workspace owner.'], 422);

        DB::table('workspace_members')->where('workspace_id', $wid)->where('user_id', $userId)->delete();
        User::where('id', $userId)->where('active_workspace_id', $wid)->update(['active_workspace_id' => null]);

        return response()->json(['message' => 'Team member removed.']);
    }

    public function cancelInvite(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        DB::table('workspace_invites')->where('workspace_id', $request->user()->active_workspace_id)->where('id', $id)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
        return response()->json(['message' => 'Invitation cancelled.']);
    }

    public function resendInvite(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $wid = $request->user()->active_workspace_id;
        $invite = DB::table('workspace_invites')->where('workspace_id', $wid)->where('id', $id)->first();
        if (!$invite) return response()->json(['message' => 'Invitation not found.'], 404);

        DB::table('workspace_invites')->where('id', $id)->update([
            'status' => 'pending', 'expires_at' => now()->addDays(7), 'updated_at' => now(),
        ]);
        $this->sendInviteMail(Workspace::findOrFail($wid), $invite->email, $invite->token);

        return response()->json(['message' => "Invitation re-sent to {$invite->email}."]);
    }

    private function sendInviteMail(Workspace $workspace, string $email, string $token): void
    {
        $app = config('app.name');
        $subject = "You're invited to {$workspace->name} on {$app}";
        $text = "You've been invited to join {$workspace->name} on {$app}.\n\nAccept your invitation: " . url("/invite/{$token}") . "\n\nThis invitation expires in 7 days.";
        try {
            $account = EmailAccount::where('workspace_id', $workspace->id)->where('status', 'connected')
                ->orderByDesc('is_default')->orderBy('id')->first();
            if ($account) {
                app(\App\Services\Email\EmailSendService::class)->send($account, $email, $subject, nl2br(e($text)));
            } else {
                Mail::raw($text, fn ($m) => $m->to($email)->subject($subject));
            }
        } catch (\Throwable $e) {
            \Log::warning('Mobile API: failed to send invite email', ['error' => $e->getMessage()]);
        }
    }
}
