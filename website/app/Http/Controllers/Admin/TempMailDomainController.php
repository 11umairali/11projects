<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncTempMailJob;
use App\Models\SystemSetting;
use App\Models\TempMailDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webklex\PHPIMAP\ClientManager;

class TempMailDomainController extends Controller
{
    /**
     * List domains with stats, search, filter by status.
     * Also load global settings for the Settings tab.
     */
    public function index(Request $request): View
    {
        $query = TempMailDomain::withCount('activeAddresses')->orderBy('domain');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $domains = $query->get();

        $totalDomains = TempMailDomain::count();
        $activeDomains = TempMailDomain::where('status', 'active')->count();
        $totalActiveAddresses = \App\Models\TempMailAddress::where('is_active', true)
            ->where('expires_at', '>', now())
            ->count();

        // Global settings for the Settings tab
        $settings = [
            'temp_mail_global_enabled'        => SystemSetting::get('temp_mail_global_enabled', 'true'),
            'temp_mail_default_lifetime_hours' => SystemSetting::get('temp_mail_default_lifetime_hours', '24'),
            'temp_mail_auto_cleanup_days'      => SystemSetting::get('temp_mail_auto_cleanup_days', '30'),
            'temp_mail_blocked_keywords'       => SystemSetting::get('temp_mail_blocked_keywords', ''),
        ];

        return view('admin.temp-mail.index', compact(
            'domains', 'totalDomains', 'activeDomains', 'totalActiveAddresses', 'settings'
        ));
    }

    /**
     * Store a new domain.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain'                => ['required', 'string', 'max:255', 'unique:temp_mail_domains,domain'],
            'display_name'          => ['required', 'string', 'max:255'],
            'imap_host'             => ['required', 'string', 'max:255'],
            'imap_port'             => ['required', 'integer'],
            'imap_username'         => ['required', 'string', 'max:255'],
            'imap_password'         => ['nullable', 'string', 'max:255'],
            'imap_encryption'       => ['required', 'in:ssl,tls,none'],
            'default_lifetime_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'max_addresses'         => ['nullable', 'integer', 'min:1'],
            'blocked_patterns'      => ['nullable', 'string'],
        ]);

        // Convert comma-separated blocked_patterns to array
        if (!empty($validated['blocked_patterns'])) {
            $validated['blocked_patterns'] = array_map('trim', explode(',', $validated['blocked_patterns']));
            $validated['blocked_patterns'] = array_filter($validated['blocked_patterns']);
        } else {
            $validated['blocked_patterns'] = [];
        }

        $validated['status'] = 'active';

        TempMailDomain::create($validated);

        return back()->with('success', "Domain \"{$validated['domain']}\" has been created.");
    }

    /**
     * Update an existing domain.
     */
    public function update(Request $request, TempMailDomain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'domain'                => ['required', 'string', 'max:255', Rule::unique('temp_mail_domains', 'domain')->ignore($domain->id)],
            'display_name'          => ['required', 'string', 'max:255'],
            'imap_host'             => ['required', 'string', 'max:255'],
            'imap_port'             => ['required', 'integer'],
            'imap_username'         => ['required', 'string', 'max:255'],
            'imap_password'         => ['nullable', 'string', 'max:255'],
            'imap_encryption'       => ['required', 'in:ssl,tls,none'],
            'default_lifetime_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'max_addresses'         => ['nullable', 'integer', 'min:1'],
            'blocked_patterns'      => ['nullable', 'string'],
        ]);

        // Don't overwrite password if empty
        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
        }

        // Convert comma-separated blocked_patterns to array
        if (!empty($validated['blocked_patterns'])) {
            $validated['blocked_patterns'] = array_map('trim', explode(',', $validated['blocked_patterns']));
            $validated['blocked_patterns'] = array_filter($validated['blocked_patterns']);
        } else {
            $validated['blocked_patterns'] = [];
        }

        $domain->update($validated);

        return back()->with('success', "Domain \"{$domain->domain}\" has been updated.");
    }

    /**
     * Delete a domain only if no active addresses.
     */
    public function destroy(TempMailDomain $domain): RedirectResponse
    {
        $activeCount = $domain->activeAddresses()->count();

        if ($activeCount > 0) {
            return back()->with('error', "Cannot delete \"{$domain->domain}\" — it has {$activeCount} active address(es). Deactivate them first.");
        }

        $name = $domain->domain;
        $domain->delete();

        return back()->with('success', "Domain \"{$name}\" has been deleted.");
    }

    /**
     * Toggle domain status between active/inactive.
     */
    public function toggleActive(TempMailDomain $domain): RedirectResponse
    {
        $newStatus = $domain->status === 'active' ? 'inactive' : 'active';
        $domain->update([
            'status' => $newStatus,
            'error_message' => $newStatus === 'active' ? null : $domain->error_message,
        ]);

        return back()->with('success', "Domain \"{$domain->domain}\" has been " . ($newStatus === 'active' ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Test IMAP connection using webklex/php-imap.
     */
    public function testConnection(TempMailDomain $domain): RedirectResponse
    {
        try {
            $cm = new ClientManager();

            $encryption = $domain->imap_encryption;
            if ($encryption === 'none') {
                $encryption = false;
            }

            $client = $cm->make([
                'host'          => $domain->imap_host,
                'port'          => $domain->imap_port,
                'encryption'    => $encryption,
                'validate_cert' => true,
                'username'      => $domain->imap_username,
                'password'      => $domain->imap_password,
                'protocol'      => 'imap',
            ]);

            $client->connect();
            $folders = $client->getFolders();
            $client->disconnect();

            $folderCount = count($folders);

            // Clear any previous error
            if ($domain->status === 'error') {
                $domain->update(['status' => 'active', 'error_message' => null]);
            }

            return back()->with('success', "Connection to \"{$domain->domain}\" successful. Found {$folderCount} folder(s).");
        } catch (\Throwable $e) {
            $domain->update([
                'status' => 'error',
                'error_message' => \Illuminate\Support\Str::limit($e->getMessage(), 400),
            ]);

            return back()->with('error', "Connection to \"{$domain->domain}\" failed: " . \Illuminate\Support\Str::limit($e->getMessage(), 200));
        }
    }

    /**
     * Dispatch SyncTempMailJob immediately.
     */
    public function syncNow(TempMailDomain $domain): RedirectResponse
    {
        if ($domain->status !== 'active') {
            return back()->with('error', "Cannot sync \"{$domain->domain}\" — domain is not active.");
        }

        SyncTempMailJob::dispatch($domain);

        return back()->with('success', "Sync job dispatched for \"{$domain->domain}\". It will run shortly.");
    }

    /**
     * Save global temp mail settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'temp_mail_default_lifetime_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'temp_mail_auto_cleanup_days'      => ['required', 'integer', 'min:1', 'max:365'],
            'temp_mail_blocked_keywords'       => ['nullable', 'string'],
        ]);

        SystemSetting::set('temp_mail_global_enabled', $request->boolean('temp_mail_global_enabled') ? 'true' : 'false', 'temp_mail');
        SystemSetting::set('temp_mail_default_lifetime_hours', (string) $request->input('temp_mail_default_lifetime_hours'), 'temp_mail');
        SystemSetting::set('temp_mail_auto_cleanup_days', (string) $request->input('temp_mail_auto_cleanup_days'), 'temp_mail');
        SystemSetting::set('temp_mail_blocked_keywords', $request->input('temp_mail_blocked_keywords', ''), 'temp_mail');

        return back()->with('success', 'Temp Mail settings have been saved.');
    }
}
