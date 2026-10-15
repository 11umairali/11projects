<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filters and pagination.
     * Uses actual column names: event, actor_type, actor_id, actor_name
     */
    public function index(Request $request): View
    {
        $query = DB::table('audit_logs')
            ->select([
                'audit_logs.id',
                'audit_logs.auditable_type',
                'audit_logs.auditable_id',
                'audit_logs.event',
                'audit_logs.actor_type',
                'audit_logs.actor_id',
                'audit_logs.actor_name',
                'audit_logs.ip_address',
                'audit_logs.user_agent',
                'audit_logs.old_values',
                'audit_logs.new_values',
                'audit_logs.created_at',
            ]);

        // Filter by event type
        if ($event = $request->input('event')) {
            $query->where('audit_logs.event', $event);
        }

        // Filter by actor type (admin or user)
        if ($actorType = $request->input('actor_type')) {
            $query->where('audit_logs.actor_type', $actorType);
        }

        // Filter by actor ID
        if ($actorId = $request->input('actor_id')) {
            $query->where('audit_logs.actor_id', $actorId);
        }

        // Search in actor_name, event, or IP
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('audit_logs.actor_name', 'like', "%{$search}%")
                    ->orWhere('audit_logs.event', 'like', "%{$search}%")
                    ->orWhere('audit_logs.ip_address', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($from = $request->input('from')) {
            $query->whereDate('audit_logs.created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('audit_logs.created_at', '<=', $to);
        }

        $query->orderByDesc('audit_logs.created_at');

        $logs = $query->paginate(50)->withQueryString();

        // Distinct event types for the filter dropdown
        $eventTypes = DB::table('audit_logs')
            ->distinct()
            ->pluck('event')
            ->sort()
            ->values();

        return view('admin.audit-log.index', compact('logs', 'eventTypes'));
    }

    /**
     * Export audit logs as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DB::table('audit_logs')->select([
            'id', 'auditable_type', 'auditable_id', 'event',
            'actor_type', 'actor_id', 'actor_name',
            'ip_address', 'user_agent', 'old_values', 'new_values', 'created_at',
        ]);

        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }
        if ($actorType = $request->input('actor_type')) {
            $query->where('actor_type', $actorType);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('created_at')->get();

        $filename = 'audit-log-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Event', 'Actor Type', 'Actor Name', 'Target Type', 'Target ID', 'IP Address', 'Date']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    str_replace('_', ' ', ucfirst($log->event)),
                    ucfirst($log->actor_type ?? 'system'),
                    $log->actor_name ?? 'System',
                    $log->auditable_type ? class_basename($log->auditable_type) : '',
                    $log->auditable_id ?? '',
                    $log->ip_address ?? '',
                    $log->created_at,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
