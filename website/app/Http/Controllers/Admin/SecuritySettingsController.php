<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAuditLogger;
use App\Support\SecuritySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SecuritySettingsController extends Controller
{
    public function index(): View
    {
        $settings = SecuritySettings::get();

        return view('admin.security-settings.index', compact('settings'));
    }

    public function update(\App\Http\Requests\Admin\UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        unset($validated['_settings_group']);

        SecuritySettings::update($validated);

        SecurityAuditLogger::settingsChanged(auth()->user(), $request, [
            'section' => 'security',
            'changes' => array_keys($validated),
        ]);

        return back()->with('success', 'Security settings updated successfully.');
    }
}
