<?php

namespace App\Http\Responses;

use App\Http\Controllers\InviteController;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = auth()->user();

        // ── 2FA Challenge Interception ──────────────────────────────

        // If the user has 2FA enabled, log them out immediately and
        // redirect to the 2FA challenge page with their ID in session.
        if ($user->hasTwoFactorEnabled()) {
            $userId   = $user->id;
            $remember = $request->boolean('remember');

            // Log the user out so they are not authenticated until 2FA passes
            Auth::logout();

            // Store just enough to complete login after 2FA verification
            $request->session()->put('2fa:user_id', $userId);
            $request->session()->put('2fa:remember', $remember);

            // Preserve the intended URL so we can redirect after 2FA
            if ($request->session()->has('url.intended')) {
                $request->session()->put('2fa:intended', $request->session()->get('url.intended'));
            }

            if ($request->wantsJson()) {
                return response()->json(['two_factor' => true]);
            }

            return redirect()->route('two-factor.challenge');
        }

        // ── Standard login flow ─────────────────────────────────────

        \Illuminate\Support\Facades\Log::info('LoginResponse::toResponse standard login flow', [
            'user_id'    => $user?->id,
            'user_email' => $user?->email,
            'session_id' => session()->getId(),
            'has_pending_invite_token' => session()->has('pending_invite_token'),
        ]);

        // Process any pending workspace invitation from session
        $inviteRedirect = InviteController::processPendingInvite($user);
        if ($inviteRedirect) {
            \Illuminate\Support\Facades\Log::info('LoginResponse: invite consumed, redirecting via invite path');
            return $inviteRedirect;
        }

        if ($user->is_admin) {
            $home = url('/admin/dashboard');
        } elseif (!$user->hasWorkspace()) {
            $home = url('/onboarding/step-1');
        } else {
            // Auto-complete onboarding if workspace exists but not marked complete
            if ($user->activeWorkspace && !$user->activeWorkspace->onboarding_completed) {
                $user->activeWorkspace->update(['onboarding_completed' => true, 'onboarding_step' => 5]);
            }
            $home = url('/dashboard');
        }

        // FIX-121: Use explicit redirect instead of intended() to prevent open redirect
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        // Only allow intended redirect to internal paths
        $intended = session()->pull('url.intended', $home);
        if (!str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            $intended = $home;
        }

        return redirect($intended);
    }
}
