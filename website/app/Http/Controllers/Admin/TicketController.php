<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    /**
     * List all support tickets with filtering and pagination.
     */
    public function index(Request $request): View
    {
        $query = Ticket::with(['user', 'workspace', 'assignedTo'])
            ->withCount('ticketReplies');

        // Search by subject or user email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by priority
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Filter by category
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['subject', 'status', 'priority', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $tickets = $query->paginate(25)->withQueryString();

        // Stats
        $stats = [
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'waiting' => Ticket::where('status', 'waiting')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
        ];

        return view('admin.tickets.index', compact('tickets', 'stats'));
    }

    /**
     * Create a ticket from the admin modal. The "user_email" field is
     * optional — if provided we look up the matching user and set
     * user_id/workspace_id on the ticket, otherwise it lands as an
     * unattributed admin-authored ticket (user_id = null).
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'subject'    => 'required|string|max:255',
            'body'       => 'required|string|max:10000',
            'priority'   => 'nullable|string|in:low,medium,high,urgent',
            'status'     => 'nullable|string|in:open,in_progress,waiting,resolved,closed',
            'user_email' => 'nullable|email|max:255',
            'category'   => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userId = null;
        $workspaceId = null;
        if ($email = $request->input('user_email')) {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user) {
                $userId      = $user->id;
                $workspaceId = $user->active_workspace_id;
            }
        }

        $ticket = Ticket::create([
            'subject'      => $request->input('subject'),
            'body'         => $request->input('body'),
            'status'       => $request->input('status', 'open'),
            'priority'     => $request->input('priority', 'medium'),
            'category'     => $request->input('category'),
            'user_id'      => $userId,
            'workspace_id' => $workspaceId,
            'assigned_to'  => null,
        ]);

        return redirect()->route('admin.tickets.show', $ticket->id)
            ->with('success', "Ticket #{$ticket->id} created.");
    }

    /**
     * Show a single ticket with its replies.
     */
    public function show(int $id): View
    {
        $ticket = Ticket::with([
            'user',
            'workspace',
            'assignedTo',
            'ticketReplies' => fn ($q) => $q->with('user')->orderBy('created_at', 'asc'),
        ])->findOrFail($id);

        return view('admin.tickets.show', compact('ticket'));
    }

    /**
     * Reply to a ticket.
     */
    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = Ticket::findOrFail($id);
        $admin = auth()->user();

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:10000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => null, // Admin reply, no user_id
            'body' => $request->input('body'),
            'is_admin_reply' => true,
        ]);

        // Update ticket status to in_progress if it was open
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return redirect()->back()->with('success', 'Reply sent successfully.');
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:open,in_progress,waiting,resolved,closed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $ticket->update(['status' => $request->input('status')]);

        return redirect()->back()->with('success', "Ticket status updated to '{$request->input('status')}'.");
    }

    /**
     * Import tickets from a CSV file.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getPathname(), 'r');
        $header = array_map('trim', fgetcsv($handle));
        $imported = 0;

        while ($row = fgetcsv($handle)) {
            if (count($row) < count($header)) continue;
            $data = array_combine($header, $row);

            $subject = $data['Subject'] ?? $data['subject'] ?? null;
            if (!$subject) continue;

            $status = strtolower(str_replace(' ', '_', $data['Status'] ?? $data['status'] ?? 'open'));
            if (!in_array($status, ['open', 'in_progress', 'waiting', 'resolved', 'closed'])) {
                $status = 'open';
            }

            $priority = strtolower($data['Priority'] ?? $data['priority'] ?? 'medium');
            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                $priority = 'medium';
            }

            Ticket::firstOrCreate(
                ['subject' => $subject, 'user_id' => $data['User ID'] ?? $data['user_id'] ?? null],
                [
                    'body' => $data['Body'] ?? $data['body'] ?? '',
                    'status' => $status,
                    'priority' => $priority,
                    'category' => $data['Category'] ?? $data['category'] ?? null,
                ]
            );
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "$imported tickets imported.");
    }

    /**
     * Export tickets as a CSV download.
     * Supports ?template=1 to return an empty CSV with headers only.
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = ['Subject', 'User ID', 'Body', 'Priority', 'Status', 'Category'];

        if ($request->has('template')) {
            return response()->streamDownload(function () use ($headers) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);
                fclose($handle);
            }, 'tickets-template.csv', ['Content-Type' => 'text/csv']);
        }

        $query = Ticket::with(['user', 'assignedTo']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        $tickets = $query->orderByDesc('created_at')->get();

        $filename = 'tickets-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Subject', 'User', 'Email', 'Priority', 'Status', 'Category', 'Created At']);
            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->id,
                    $ticket->subject,
                    $ticket->user?->name ?? 'Unknown',
                    $ticket->user?->email ?? 'N/A',
                    ucfirst($ticket->priority),
                    ucfirst(str_replace('_', ' ', $ticket->status)),
                    ucfirst($ticket->category ?? ''),
                    $ticket->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Delete a single ticket.
     */
    public function destroy(int $id): RedirectResponse
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted.');
    }

    /**
     * Bulk delete tickets.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $count = Ticket::whereIn('id', $request->ids)->delete();
        return back()->with('success', "{$count} tickets deleted.");
    }
}
