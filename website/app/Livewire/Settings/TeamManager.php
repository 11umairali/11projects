<?php

// @deprecated Use TeamSettings instead. TeamSettings uses Eloquent models (Invite model) and
// proper relationship methods rather than raw DB queries. This component is retained for backward
// compatibility with any existing Blade views that reference it.

namespace App\Livewire\Settings;

use App\Exceptions\PlanLimitReachedException;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;
use Livewire\Component;

class TeamManager extends Component
{
    use AuthorizesWorkspaceActions;

    #[Rule('required|email|max:255')]
    public string $inviteEmail = '';

    #[Rule('required|in:admin,agent,viewer')]
    public string $inviteRole = 'agent';

    public bool $showInviteForm = false;

    public function openInviteForm(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->showInviteForm = true;
        $this->inviteEmail = '';
        $this->inviteRole = 'agent';
    }

    public function closeInviteForm(): void
    {
        $this->showInviteForm = false;
        $this->resetValidation();
    }

    public function sendInvite(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate();

        $workspaceId = auth()->user()->active_workspace_id;
        $workspace = Workspace::findOrFail($workspaceId);

        // Enforce plan limit on team members
        try {
            app(PlanLimitService::class)->assertCanCreate($workspace, 'team_members');
        } catch (PlanLimitReachedException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        // Check if user already exists and is a member
        $existingUser = User::where('email', $this->inviteEmail)->first();
        if ($existingUser) {
            $isMember = $workspace->members()->where('users.id', $existingUser->id)->exists();
            if ($isMember) {
                $this->addError('inviteEmail', 'This user is already a workspace member.');
                return;
            }
        }

        // Only block when a truly live invitation exists — pending AND not
        // yet expired. Cancelled or expired rows should not prevent a fresh
        // invite; we clean them up first so the unique (workspace_id, email)
        // index doesn't trip insertOrIgnore and leave the user staring at
        // "An invitation has already been sent" forever.
        $livePending = \DB::table('workspace_invites')
            ->where('workspace_id', $workspaceId)
            ->where('email', $this->inviteEmail)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($livePending) {
            $this->addError('inviteEmail', 'An invitation has already been sent to this email.');
            return;
        }

        // Purge stale rows (cancelled / expired / past-due) so the unique
        // index is free for a fresh insert.
        \DB::table('workspace_invites')
            ->where('workspace_id', $workspaceId)
            ->where('email', $this->inviteEmail)
            ->where(function ($q) {
                $q->where('status', '!=', 'pending')
                    ->orWhere('expires_at', '<=', now());
            })
            ->delete();

        // Atomically create the fresh invite. If a concurrent request just
        // inserted one before us, insertOrIgnore returns 0 and we show the
        // same friendly error.
        $token = Str::random(64);
        $inserted = \DB::table('workspace_invites')
            ->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'email' => $this->inviteEmail,
                'role' => $this->inviteRole,
                'token' => $token,
                'invited_by' => auth()->id(),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (!$inserted) {
            $this->addError('inviteEmail', 'An invitation has already been sent to this email.');
            return;
        }

        // Send invite email through a connected workspace email account (Gmail
        // OAuth, Outlook OAuth, or IMAP/SMTP). Falls back to Laravel's default
        // mail driver (Mail::raw) only if no account is connected — that's
        // the path that was dumping into laravel.log instead of actually
        // delivering, because the platform default is the `log` driver.
        $appName = config('app.name');
        $subject = "You're invited to {$workspace->name} on {$appName}";
        $acceptUrl = url("/invite/{$token}");
        $textBody = "You've been invited to join {$workspace->name} on {$appName}.\n\n"
            . "Accept your invitation: {$acceptUrl}\n\n"
            . "This invitation expires in 7 days.";
        $htmlBody = nl2br(e($textBody));

        try {
            $inviteEmail = $this->inviteEmail;

            $account = \App\Models\EmailAccount::where('workspace_id', $workspace->id)
                ->where('status', 'connected')
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($account) {
                app(\App\Services\Email\EmailSendService::class)
                    ->send($account, $inviteEmail, $subject, $htmlBody);
                \Log::info('TeamManager: invite sent via workspace email account', [
                    'via' => $account->email,
                    'provider' => $account->provider,
                    'to' => $inviteEmail,
                ]);
            } else {
                // No workspace account connected — fall back to the global
                // mailer. Works only if MAIL_MAILER in .env is a real
                // transport (smtp/ses/etc.), not `log`.
                Mail::raw($textBody, function ($message) use ($inviteEmail, $subject) {
                    $message->to($inviteEmail)->subject($subject);
                });
                \Log::warning('TeamManager: invite sent via global mailer fallback — no connected workspace email account', [
                    'to' => $inviteEmail,
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail if email can't be sent — invite is still created
            \Log::warning('Failed to send invite email', ['error' => $e->getMessage()]);
        }

        $sentTo = $this->inviteEmail;
        $this->closeInviteForm();
        session()->flash('success', "Invitation sent to {$sentTo}.");
    }

    public function changeRole(int $userId, string $role): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        // Can't change own role
        if ($userId === auth()->id()) {
            session()->flash('error', 'You cannot change your own role.');
            return;
        }

        // Only owners can change roles
        $currentUserRole = $this->getCurrentUserRole($workspaceId);
        if ($currentUserRole !== 'owner') {
            session()->flash('error', 'Only workspace owners can change member roles.');
            return;
        }

        // Cannot change the owner's role
        $targetRole = \DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->value('role');

        if ($targetRole === 'owner') {
            session()->flash('error', 'Cannot change the workspace owner\'s role.');
            return;
        }

        // Validate role value
        if (! in_array($role, ['admin', 'agent', 'viewer'])) {
            session()->flash('error', 'Invalid role specified.');
            return;
        }

        \DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->update(['role' => $role]);

        session()->flash('success', 'Member role updated successfully.');
    }

    public function removeMember(int $userId): void
    {
        if (! $this->authorizeWorkspaceAction('dangerous')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        // Can't remove self
        if ($userId === auth()->id()) {
            session()->flash('error', 'You cannot remove yourself. Transfer ownership first.');
            return;
        }

        // Cannot remove the owner
        $targetRole = \DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->value('role');

        if ($targetRole === 'owner') {
            session()->flash('error', 'Cannot remove the workspace owner.');
            return;
        }

        if (! $targetRole) {
            session()->flash('error', 'Member not found.');
            return;
        }

        \DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->delete();

        // If removed user's active workspace was this one, clear it
        User::where('id', $userId)
            ->where('active_workspace_id', $workspaceId)
            ->update(['active_workspace_id' => null]);

        session()->flash('success', 'Team member removed.');
    }

    public function cancelInvite(int $inviteId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Enum is 'cancelled' (British spelling) per the migration — using
        // 'canceled' here truncated silently to '' and threw the SQLSTATE
        // 01000 "Data truncated for column 'status'" error.
        \DB::table('workspace_invites')
            ->where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $inviteId)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        session()->flash('success', 'Invitation cancelled.');
    }

    public function resendInvite(int $inviteId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $invite = \DB::table('workspace_invites')
            ->where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $inviteId)
            ->first();

        if (! $invite) {
            session()->flash('error', 'Invitation not found.');
            return;
        }

        $workspace = Workspace::find($invite->workspace_id);

        \DB::table('workspace_invites')
            ->where('id', $inviteId)
            ->update(['expires_at' => now()->addDays(7), 'updated_at' => now()]);

        // Same delivery strategy as the initial invite: prefer a connected
        // workspace email account (Gmail OAuth / Outlook OAuth / IMAP-SMTP).
        // Only fall back to Laravel's global mailer when none is configured.
        $appName = config('app.name');
        $subject = "Reminder: You're invited to {$workspace->name} on {$appName}";
        $acceptUrl = url("/invite/{$invite->token}");
        $textBody = "Reminder: You've been invited to join {$workspace->name} on {$appName}.\n\n"
            . "Accept your invitation: {$acceptUrl}\n\n"
            . "This invitation expires in 7 days.";
        $htmlBody = nl2br(e($textBody));

        try {
            $account = \App\Models\EmailAccount::where('workspace_id', $workspace->id)
                ->where('status', 'connected')
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($account) {
                app(\App\Services\Email\EmailSendService::class)
                    ->send($account, $invite->email, $subject, $htmlBody);
                \Log::info('TeamManager: invite reminder sent via workspace email account', [
                    'via' => $account->email,
                    'provider' => $account->provider,
                    'to' => $invite->email,
                ]);
            } else {
                Mail::raw($textBody, function ($message) use ($invite, $subject) {
                    $message->to($invite->email)->subject($subject);
                });
                \Log::warning('TeamManager: invite reminder sent via global mailer fallback — no connected workspace email account', [
                    'to' => $invite->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to resend invite email', ['error' => $e->getMessage()]);
        }

        session()->flash('success', "Invitation resent to {$invite->email}.");
    }

    private function getCurrentUserRole(int $workspaceId): ?string
    {
        return \DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', auth()->id())
            ->value('role');
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $members = \DB::table('workspace_members')
            ->join('users', 'users.id', '=', 'workspace_members.user_id')
            ->where('workspace_members.workspace_id', $workspaceId)
            ->select('users.id', 'users.name', 'users.email', 'users.avatar_path',
                'workspace_members.role', 'workspace_members.created_at')
            ->orderByRaw("FIELD(workspace_members.role, 'owner', 'admin', 'agent', 'viewer')")
            ->get();

        $pendingInvites = \DB::table('workspace_invites')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.settings.team-manager', [
            'members' => $members,
            'pendingInvites' => $pendingInvites,
        ]);
    }
}
