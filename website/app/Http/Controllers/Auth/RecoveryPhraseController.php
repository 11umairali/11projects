<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows the recovery phrase exactly once, right after signup — ported
 * from the webmail app. The only change from the original is the final
 * redirect: instead of a fixed "mail.folder" route, it continues on to
 * wherever RegisterResponse would have sent the user (onboarding,
 * dashboard, or the admin panel), which it stashed in the session.
 */
class RecoveryPhraseController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $words = $request->session()->get('recovery_phrase');

        if (! $words) {
            return redirect($request->session()->pull('recovery_phrase_next', '/dashboard'));
        }

        return view('auth.recovery-phrase', ['words' => $words]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['saved' => ['accepted']], [
            'saved.accepted' => 'Please confirm you have saved your recovery phrase.',
        ]);

        $regenerated = $request->session()->pull('recovery_phrase_context') === 'regenerated';
        $request->session()->forget('recovery_phrase');
        $next = $request->session()->pull('recovery_phrase_next', '/dashboard');

        return $regenerated
            ? redirect($next)->with('status', 'Your new recovery phrase is active. The old one no longer works.')
            : redirect($next)->with('status', 'Welcome to '.config('app.name').'! Your address is ready.');
    }
}
