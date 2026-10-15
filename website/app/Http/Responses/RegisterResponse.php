<?php

namespace App\Http\Responses;

use App\Http\Controllers\InviteController;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        $user = auth()->user();

        \Illuminate\Support\Facades\Log::info('RegisterResponse::toResponse ENTER', [
            'user_id'    => $user?->id,
            'user_email' => $user?->email,
            'session_id' => session()->getId(),
            'has_pending_invite_token' => session()->has('pending_invite_token'),
        ]);

        // If the user just registered after clicking a workspace invite
        // link, the token is sitting in the session. Hand it off to the
        // invite controller so they're added to the inviting workspace
        // instead of being dropped into the new-workspace onboarding
        // flow (which would orphan the invite).
        $inviteRedirect = InviteController::processPendingInvite($user);
        if ($inviteRedirect) {
            \Illuminate\Support\Facades\Log::info('RegisterResponse: invite consumed, redirecting via invite path');
            return $inviteRedirect;
        }

        \Illuminate\Support\Facades\Log::info('RegisterResponse: no invite consumed — falling through to default destination');

        // No pending invite — fall back to the same destination logic
        // LoginResponse uses for ordinary signups.
        if ($user->is_admin) {
            $home = url('/admin/dashboard');
        } elseif (! $user->hasWorkspace()) {
            $home = url('/onboarding/step-1');
        } else {
            $home = url('/dashboard');
        }

        // Show the recovery phrase exactly once, right after signup —
        // same as the webmail app. It's the only way back into the
        // account if the password is ever lost, since there's no
        // separate recovery email on file yet.
        if ($request->session()->has('recovery_phrase')) {
            $request->session()->put('recovery_phrase_next', $home);

            $destination = route('recovery-phrase.show');

            if ($request->wantsJson()) {
                return response()->json(['redirect' => $destination]);
            }

            return redirect($destination);
        }

        if ($request->wantsJson()) {
            return response()->json(['redirect' => $home]);
        }

        return redirect($home);
    }
}
