<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = SecurityAuditLog::with('user')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $eventTypes = SecurityAuditLog::select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        $statuses = SecurityAuditLog::select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        // Stats
        $stats = [
            'total' => SecurityAuditLog::count(),
            'today' => SecurityAuditLog::whereDate('created_at', today())->count(),
            'failed' => SecurityAuditLog::where('status', 'failed')->whereDate('created_at', today())->count(),
            'blocked' => SecurityAuditLog::where('status', 'blocked')->whereDate('created_at', today())->count(),
        ];

        return view('admin.security-audit-logs.index', compact('logs', 'eventTypes', 'statuses', 'stats'));
    }

    public function show(SecurityAuditLog $securityAuditLog): View
    {
        $securityAuditLog->load('user');
        return view('admin.security-audit-logs.show', ['log' => $securityAuditLog]);
    }

    public function destroy(SecurityAuditLog $securityAuditLog): RedirectResponse
    {
        // Direct delete bypasses the immutability guard on save()
        SecurityAuditLog::where('id', $securityAuditLog->id)->delete();

        return back()->with('success', 'Audit log entry deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $count = SecurityAuditLog::whereIn('id', $request->input('ids'))->delete();

        return back()->with('success', "{$count} log(s) deleted.");
    }

    public function export(Request $request): StreamedResponse
    {
        $query = SecurityAuditLog::with('user')->latest();

        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $logs = $query->get();

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Event', 'Status', 'User', 'Email', 'IP', 'User Agent', 'Metadata']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->toDateTimeString(),
                    $log->event_type,
                    $log->status,
                    $log->user?->name ?? 'N/A',
                    $log->user?->email ?? 'N/A',
                    $log->ip_address,
                    $log->user_agent,
                    json_encode($log->metadata),
                ]);
            }

            fclose($handle);
        }, 'security_audit_' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
