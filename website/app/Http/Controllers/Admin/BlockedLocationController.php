<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedLocation;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlockedLocationController extends Controller
{
    public function index(Request $request): View
    {
        $query = BlockedLocation::with('creator')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('country_name', 'like', "%{$search}%")
                  ->orWhere('country_code', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $locations = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => BlockedLocation::count(),
            'active' => BlockedLocation::where('is_active', true)->count(),
            'countries' => BlockedLocation::where('is_active', true)->distinct('country_code')->count('country_code'),
        ];

        $blockedLocations = $locations;
        return view('admin.blocked-locations.index', compact('blockedLocations', 'stats'));
    }

    public function create(): View
    {
        return view('admin.blocked-locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_code' => 'required|string|max:5',
            'country_name' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();

        BlockedLocation::create($validated);

        SecurityAuditLogger::log('location_blocked', 'success', auth()->user(), $request, [
            'country' => $validated['country_name'],
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
        ]);

        return redirect()->route('admin.blocked-locations.index')
            ->with('success', 'Location blocked successfully.');
    }

    public function edit(BlockedLocation $blockedLocation): View
    {
        return view('admin.blocked-locations.edit', ['location' => $blockedLocation]);
    }

    public function update(Request $request, BlockedLocation $blockedLocation): RedirectResponse
    {
        $validated = $request->validate([
            'country_code' => 'required|string|max:5',
            'country_name' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $blockedLocation->update($validated);

        return redirect()->route('admin.blocked-locations.index')
            ->with('success', 'Blocked location updated.');
    }

    public function destroy(BlockedLocation $blockedLocation): RedirectResponse
    {
        $blockedLocation->delete();
        return back()->with('success', 'Blocked location removed.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $count = BlockedLocation::whereIn('id', $request->input('ids'))->delete();
        return back()->with('success', "{$count} location(s) removed.");
    }

    public function export(): StreamedResponse
    {
        $locations = BlockedLocation::with('creator')->orderBy('country_name')->get();

        return response()->streamDownload(function () use ($locations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Country Code', 'Country Name', 'State', 'City', 'Reason', 'Active', 'Created By']);

            foreach ($locations as $loc) {
                fputcsv($handle, [
                    $loc->country_code, $loc->country_name, $loc->state, $loc->city,
                    $loc->reason, $loc->is_active ? '1' : '0', $loc->creator?->name ?? 'N/A',
                ]);
            }

            fclose($handle);
        }, 'blocked_locations_' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        fgetcsv($handle); // skip header
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            BlockedLocation::updateOrCreate(
                [
                    'country_code' => trim($row[0]),
                    'state' => trim($row[2] ?? '') ?: null,
                    'city' => trim($row[3] ?? '') ?: null,
                ],
                [
                    'country_name' => trim($row[1]),
                    'reason' => trim($row[4] ?? '') ?: null,
                    'is_active' => ($row[5] ?? '1') == '1',
                    'created_by' => auth()->id(),
                ]
            );
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "{$imported} location(s) imported.");
    }
}
