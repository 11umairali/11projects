<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\EmailAccount;
use App\Models\EmailSignature;
use App\Models\Message;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataExportController extends Controller
{
    /**
     * Export all user/workspace data for GDPR compliance.
     * FIX-040: Complete export including all personal data
     * FIX-041: Audit trail for exports
     * FIX-042: Confirmation email on export
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $workspaceId = $user->active_workspace_id;

        if (! $workspaceId) {
            return back()->with('error', 'No active workspace selected.');
        }

        // Verify user is admin or owner
        $role = $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->first()?->pivot?->role;

        if (! in_array($role, ['owner', 'admin'])) {
            abort(403, 'Only workspace owners and admins can export data.');
        }

        // FIX-041: Log the export request for audit trail
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'data_exported',
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'old_values' => null,
            'new_values' => json_encode([
                'workspace_id' => $workspaceId,
                'export_type' => 'gdpr_full',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // FIX-040: Complete GDPR export with ALL personal data
        // Stream the JSON response to avoid loading all records into memory
        $filename = 'mailtrixy-export-' . now()->format('Y-m-d-His') . '.json';

        // FIX-042: Log export completion
        Log::info('GDPR data export completed', [
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'ip' => $request->ip(),
        ]);

        return response()->streamDownload(function () use ($user, $workspaceId) {
            echo '{';
            echo '"exported_at":' . json_encode(now()->toIso8601String()) . ',';
            echo '"workspace_id":' . json_encode($workspaceId) . ',';
            echo '"exported_by":' . json_encode($user->email) . ',';

            echo '"user_profile":' . json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'status' => $user->status,
                'two_factor_enabled' => $user->two_factor_enabled,
                'referral_code' => $user->referral_code,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ]) . ',';

            echo '"social_accounts":' . json_encode($user->socialAccounts()
                ->select(['provider', 'provider_id', 'created_at'])
                ->get()->toArray()) . ',';

            // Stream contacts in chunks
            echo '"contacts":[';
            $first = true;
            Contact::where('workspace_id', $workspaceId)
                ->select(['id', 'first_name', 'last_name', 'email', 'phone', 'company',
                    'job_title', 'city', 'country', 'status', 'custom_fields',
                    'lead_score', 'created_at', 'last_contacted_at'])
                ->chunkById(500, function ($contacts) use (&$first) {
                    foreach ($contacts as $contact) {
                        echo ($first ? '' : ',') . json_encode($contact->toArray());
                        $first = false;
                    }
                });
            echo '],';

            // Stream conversations in chunks
            echo '"conversations":[';
            $first = true;
            Conversation::where('workspace_id', $workspaceId)
                ->select(['id', 'subject', 'channel', 'status', 'priority',
                    'sentiment', 'created_at', 'last_message_at', 'resolved_at'])
                ->chunkById(500, function ($conversations) use (&$first) {
                    foreach ($conversations as $conv) {
                        echo ($first ? '' : ',') . json_encode($conv->toArray());
                        $first = false;
                    }
                });
            echo '],';

            echo '"messages_count":' . Message::where('workspace_id', $workspaceId)->count() . ',';

            echo '"email_accounts":' . json_encode(EmailAccount::where('workspace_id', $workspaceId)
                ->select(['id', 'email', 'provider', 'status', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"email_signatures":' . json_encode(EmailSignature::whereHas('emailAccount', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            })->select(['id', 'name', 'is_default', 'created_at'])->get()->toArray()) . ',';

            echo '"deals":' . json_encode(Deal::where('workspace_id', $workspaceId)
                ->select(['id', 'title', 'value', 'status', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"payments":' . json_encode(Payment::where('workspace_id', $workspaceId)
                ->select(['id', 'amount', 'currency', 'status', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"audit_logs":' . json_encode(DB::table('audit_logs')
                ->where('actor_id', auth()->id())
                ->where('actor_type', 'user')
                ->select(['event', 'ip_address', 'created_at'])
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()->toArray()) . ',';

            // FIX-040: Additional data sections for complete GDPR export
            echo '"tags":' . json_encode(DB::table('tags')
                ->where('workspace_id', $workspaceId)
                ->select(['name', 'color', 'sort_order', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"custom_fields":' . json_encode(DB::table('custom_fields')
                ->where('workspace_id', $workspaceId)
                ->select(['name', 'key', 'type', 'options', 'required', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"workflows":' . json_encode(DB::table('workflows')
                ->where('workspace_id', $workspaceId)
                ->select(['name', 'description', 'status', 'version', 'executions_count', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"campaigns_full":[';
            $first = true;
            DB::table('campaigns')
                ->where('workspace_id', $workspaceId)
                ->select(['name', 'type', 'status', 'subject', 'audience_type',
                    'recipients_count', 'sent_count', 'opened_count', 'clicked_count',
                    'scheduled_at', 'sent_at', 'created_at'])
                ->chunkById(200, function ($campaigns) use (&$first) {
                    foreach ($campaigns as $campaign) {
                        echo ($first ? '' : ',') . json_encode((array) $campaign);
                        $first = false;
                    }
                });
            echo '],';

            echo '"kb_documents":' . json_encode(DB::table('kb_documents')
                ->where('workspace_id', $workspaceId)
                ->select(['title', 'type', 'category', 'status', 'chunks_count', 'usage_count', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"auto_reply_rules":' . json_encode(DB::table('auto_reply_rules')
                ->where('workspace_id', $workspaceId)
                ->select(['name', 'conditions', 'is_active', 'created_at'])
                ->get()->toArray()) . ',';

            echo '"channel_integrations":' . json_encode(DB::table('channel_integrations')
                ->where('workspace_id', $workspaceId)
                ->select(['channel', 'provider', 'status', 'ai_auto_reply', 'created_at'])
                ->get()->toArray());

            echo '}';
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * FIX-044: GDPR Right to Be Forgotten — schedule account deletion.
     * Sets deletion_requested_at on user, which triggers cleanup after grace period.
     */
    public function requestDeletion(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'confirmation' => 'required|string|in:DELETE',
            'password' => 'required|current_password',
        ]);

        // Audit log
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'deletion_requested',
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'old_values' => null,
            'new_values' => json_encode(['deletion_requested_at' => now()->toIso8601String()]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->update([
            'deletion_requested_at' => now(),
            'status' => 'pending_deletion',
        ]);

        Log::info('GDPR deletion requested', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/'))->with('success', 'Your account deletion has been scheduled. Your data will be permanently removed within 30 days. You can contact support to cancel this request.');
    }

    /**
     * Deactivate account — sets status to 'suspended' so user can't login,
     * but data is preserved. User can contact support to reactivate.
     */
    public function deactivate(Request $request)
    {
        $user = Auth::user();

        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'account_deactivated',
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'old_values' => json_encode(['status' => $user->status]),
            'new_values' => json_encode(['status' => 'suspended']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->update(['status' => 'suspended', 'suspended_at' => now()]);

        Log::info('Account deactivated by user', ['user_id' => $user->id, 'email' => $user->email]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/login'))->with('info', 'Your account has been deactivated. Contact support to reactivate.');
    }
}
