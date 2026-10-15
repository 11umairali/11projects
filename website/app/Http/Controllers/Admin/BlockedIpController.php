<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlockedIpController extends Controller
{
    /**
     * List blocked IPs with pagination.
     */
    public function index(Request $request): View
    {
        $query = DB::table('blocked_ips')
            ->orderByDesc('created_at');

        // Search by IP or reason
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('blocked_by', 'like', "%{$search}%");
            });
        }

        $blockedIps = $query->paginate(25)->withQueryString();

        // Stats
        $totalBlocked  = DB::table('blocked_ips')->count();
        $permanent     = DB::table('blocked_ips')->whereNull('blocked_until')->count();
        $activeTemp    = DB::table('blocked_ips')
            ->whereNotNull('blocked_until')
            ->where('blocked_until', '>', now())
            ->count();
        $expired       = DB::table('blocked_ips')
            ->whereNotNull('blocked_until')
            ->where('blocked_until', '<=', now())
            ->count();

        return view('admin.blocked-ips.index', compact(
            'blockedIps',
            'totalBlocked',
            'permanent',
            'activeTemp',
            'expired',
        ));
    }

    /**
     * Block a new IP address.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason'     => ['nullable', 'string', 'max:255'],
            'duration'   => ['required', 'string', 'in:permanent,1h,24h,7d,30d'],
        ]);

        $ip = $request->input('ip_address');

        // Prevent blocking your own IP
        if ($ip === $request->ip()) {
            return back()->with('error', 'You cannot block your own IP address.');
        }

        // Calculate expiry
        $blockedUntil = match ($request->input('duration')) {
            '1h'   => now()->addHour(),
            '24h'  => now()->addDay(),
            '7d'   => now()->addWeek(),
            '30d'  => now()->addMonth(),
            default => null, // permanent
        };

        // Upsert: update if IP already exists, insert otherwise
        $existing = DB::table('blocked_ips')->where('ip_address', $ip)->first();

        if ($existing) {
            DB::table('blocked_ips')
                ->where('id', $existing->id)
                ->update([
                    'reason'        => $request->input('reason'),
                    'blocked_until' => $blockedUntil,
                    'blocked_by'    => auth()->user()->name ?? 'Admin',
                    'updated_at'    => now(),
                ]);
        } else {
            DB::table('blocked_ips')->insert([
                'ip_address'    => $ip,
                'reason'        => $request->input('reason'),
                'blocked_until' => $blockedUntil,
                'blocked_by'    => auth()->user()->name ?? 'Admin',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Clear the cache for this IP so the block takes effect immediately
        Cache::forget("blocked_ip:{$ip}");

        return back()->with('success', "IP address {$ip} has been blocked.");
    }

    /**
     * Unblock (delete) a blocked IP.
     */
    public function destroy(int $id): RedirectResponse
    {
        $record = DB::table('blocked_ips')->where('id', $id)->first();

        if (! $record) {
            return back()->with('error', 'Blocked IP record not found.');
        }

        DB::table('blocked_ips')->where('id', $id)->delete();

        // Clear cache so the unblock takes effect immediately
        Cache::forget("blocked_ip:{$record->ip_address}");

        return back()->with('success', "IP address {$record->ip_address} has been unblocked.");
    }

    /**
     * Bulk unblock (delete) multiple blocked IPs.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $records = DB::table('blocked_ips')->whereIn('id', $request->ids)->get();

        // Clear cache for each IP
        foreach ($records as $record) {
            Cache::forget("blocked_ip:{$record->ip_address}");
        }

        $count = DB::table('blocked_ips')->whereIn('id', $request->ids)->delete();

        return back()->with('success', "{$count} blocked IPs removed.");
    }
}
