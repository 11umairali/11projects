<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use App\Services\EmailAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InviteController extends Controller
{
    /**
     * Accept a workspace invitation via token link.
     */
    public function accept(string $token): RedirectResponse
    {
        Log::info('Invite::accept ENTER', [
            'token_first8' => substr($token, 0, 8),
            'auth_check'   => Auth::check(),
            'auth_email'   => Auth::user()->email ?? null,
            'session_id'   => session()->getId(),
        ]);

        // Resolve the invite first WITHOUT the status filter so we can give
        // a precise error when it's already accepted/cancelled, instead of
        // silently bouncing to /login → /dashboard which reads as "nothing
        // happened" when the user is already authenticated.
        $invite = Invite::where('token', $token)->first();

        if (! $invite) {
            Log::warning('Invite::accept token not found', ['token_first8' => substr($token, 0, 8)]);
            return $this->bounce('error', 'This invitation link is invalid.');
        }

        Log::info('Invite::accept invite resolved', [
            'invite_id'  => $invite->id,
            'email'      => $invite->email,
            'status'     => $invite->status,
            'workspace'  => $invite->workspace_id,
            'expires_at' => (string) $invite->expires_at,
        ]);

        if ($invite->status === 'accepted') {
            Log::info('Invite::accept already accepted, bouncing');
            return $this->bounce('info', 'This invitation has already been used.');
        }

        if ($invite->status === 'cancelled') {
            return $this->bounce('error', 'This invitation was cancelled by the workspace owner.');
        }

        if ($invite->status !== 'pending') {
            return $this->bounce('error', 'This invitation is no longer valid.');
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            $invite->update(['status' => 'expired']);
            Log::info('Invite::accept expired', ['invite_id' => $invite->id]);
            return $this->bounce('error', 'This invitation has expired. Please ask for a new one.');
        }

        // ── Authed branch ─────────────────────────────────────────────
        if (Auth::check()) {
            $current = Auth::user();

            if (strtolower($current->email) === strtolower($invite->email)) {
                Log::info('Invite::accept authed-same-email, processing now', [
                    'user_id' => $current->id,
                ]);
                return $this->processAcceptance($invite, $current);
            }

            Log::info('Invite::accept authed-WRONG-email, signing out and saving token', [
                'auth_email'   => $current->email,
                'invite_email' => $invite->email,
            ]);

            session(['pending_invite_token' => $token]);
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            // Re-set after regenerate (invalidate clears the session payload).
            session(['pending_invite_token' => $token]);

            return redirect()->route('login')->with(
                'info',
                'This invitation was sent to ' . $invite->email
                    . '. You\'ve been signed out — please sign in as that address (or register it) to accept.'
            );
        }

        // ── Guest branch ──────────────────────────────────────────────
        session(['pending_invite_token' => $token]);
        session()->save();

        Log::info('Invite::accept guest, saved token to session', [
            'session_id' => session()->getId(),
            'has_token_after_save' => session()->has('pending_invite_token'),
        ]);

        $existingUser = User::where('email', $invite->email)->first();

        if ($existingUser) {
            Log::info('Invite::accept guest, account exists, → /login');
            return redirect()->route('login')
                ->with('info', 'Please log in as ' . $invite->email . ' to accept your workspace invitation.');
        }

        Log::info('Invite::accept guest, no account, → /register');
        return redirect()->route('register')
            ->with('info', 'Create an account to accept your workspace invitation.');
    }

    /**
     * Always-visible bounce for "this link can't be used right now"
     * cases. Logged-in users land on /dashboard with the toast; guests
     * land on /login with the toast.
     */
    protected function bounce(string $kind, string $message): RedirectResponse
    {
        $target = Auth::check() ? url('/dashboard') : route('login');
        return redirect($target)->with($kind, $message);
    }

    /**
     * Process invitation acceptance after authentication.
     * Called from LoginResponse when pending_invite_token exists in session.
     */
    public static function processPendingInvite(User $user): ?RedirectResponse
    {
        $token = session('pending_invite_token');

        Log::info('Invite::processPendingInvite ENTER', [
            'user_id'    => $user->id,
            'user_email' => $user->email,
            'has_token'  => (bool) $token,
            'session_id' => session()->getId(),
        ]);

        if (! $token) {
            Log::info('Invite::processPendingInvite no token in session — falling through to default redirect');
            return null;
        }

        session()->forget('pending_invite_token');

        $invite = Invite::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $invite) {
            Log::warning('Invite::processPendingInvite token in session but NO pending invite row — was it already accepted/cancelled?', [
                'token_first8' => substr($token, 0, 8),
            ]);
            return null;
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            $invite->update(['status' => 'expired']);
            Log::warning('Invite::processPendingInvite invite is expired', ['invite_id' => $invite->id]);
            return null;
        }

        Log::info('Invite::processPendingInvite handing off to processAcceptance', [
            'invite_id'    => $invite->id,
            'invite_email' => $invite->email,
            'workspace_id' => $invite->workspace_id,
        ]);

        return (new static)->processAcceptance($invite, $user);
    }

    /**
     * Process the actual workspace join.
     */
    protected function processAcceptance(Invite $invite, User $user): RedirectResponse
    {
        Log::info('Invite::processAcceptance ENTER', [
            'user_id'      => $user->id,
            'user_email'   => $user->email,
            'invite_email' => $invite->email,
            'workspace_id' => $invite->workspace_id,
            'role'         => $invite->role,
        ]);

        // Verify email matches
        if (strtolower($user->email) !== strtolower($invite->email)) {
            Log::warning('Invite::processAcceptance EMAIL MISMATCH — refusing to attach', [
                'user_email'   => $user->email,
                'invite_email' => $invite->email,
            ]);
            return redirect(url('/dashboard'))
                ->with('error', 'This invitation was sent to a different email address (' . $invite->email . ').');
        }

        $workspace = Workspace::find($invite->workspace_id);

        if (! $workspace) {
            $invite->update(['status' => 'expired']);
            Log::error('Invite::processAcceptance workspace gone, marking invite expired', [
                'invite_id'    => $invite->id,
                'workspace_id' => $invite->workspace_id,
            ]);
            return redirect(url('/dashboard'))
                ->with('error', 'The workspace for this invitation no longer exists.');
        }

        // Check if already a member
        $alreadyMember = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->exists();

        if ($alreadyMember) {
            $invite->update(['status' => 'accepted']);
            Log::info('Invite::processAcceptance user is already a member', [
                'user_id'      => $user->id,
                'workspace_id' => $workspace->id,
            ]);
            return redirect(url('/dashboard'))
                ->with('info', 'You are already a member of "' . $workspace->name . '".');
        }

        // SEC-009: Validate and sanitize the invited role — prevent privilege escalation
        $allowedRoles = ['viewer', 'agent', 'admin'];
        $role = $invite->role ?? 'agent';
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'agent';
        }

        DB::transaction(function () use ($user, $workspace, $invite, $role) {
            // The pivot (workspace_members) doesn't have a `joined_at`
            // column — only `role`, `status`, plus the standard
            // created_at/updated_at timestamps that Eloquent fills in
            // automatically. Passing `joined_at` here triggered a
            // SQLSTATE[42S22] "Unknown column 'joined_at'" on insert.
            $workspace->members()->attach($user->id, [
                'role' => $role,
            ]);

            $invite->update(['status' => 'accepted']);

            if (! $user->active_workspace_id) {
                $user->update(['active_workspace_id' => $workspace->id]);
            }

            // Same as onboarding step 1: wire up this user's built-in
            // @dahimail.com mailbox as an EmailAccount in the workspace
            // they just joined.
            try {
                app(EmailAccountProvisioner::class)->ensure($user, $workspace);
            } catch (\Throwable $e) {
                Log::warning('EmailAccountProvisioner failed during invite acceptance', [
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        Log::info('Invite::processAcceptance JOINED OK', [
            'user_id'      => $user->id,
            'workspace_id' => $workspace->id,
            'role'         => $role,
        ]);

        return redirect(url('/dashboard'))
            ->with('success', 'You have joined the "' . $workspace->name . '" workspace!');
    }
}
