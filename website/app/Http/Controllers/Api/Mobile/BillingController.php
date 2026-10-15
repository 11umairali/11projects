<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\KbDocument;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Billing overview (plan, usage, invoices) + a one-time hand-off link that
 * opens the real web checkout / OAuth pages in the system browser already
 * signed in. Payment itself stays on the web (Stripe & other gateways).
 * Mirrors Livewire\Settings\BillingManager.
 */
class BillingController extends Controller
{
    use AuthorizesApiActions;

    public function overview(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $user = $request->user();
        $wid = $user->active_workspace_id;
        $workspace = $user->activeWorkspace;

        $sub = Subscription::where('workspace_id', $wid)->with('plan')->latest()->first();
        $current = $sub?->plan;

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->with('planFeatures')->get()->map(fn ($p) => [
            'id'            => $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'description'   => $p->description,
            'monthly_price' => (float) $p->monthly_price,
            'yearly_price'  => (float) $p->yearly_price,
            'is_popular'    => (bool) $p->is_popular,
            'is_free'       => $p->isFree(),
            'is_current'    => $current && $current->id === $p->id,
            'features'      => $p->planFeatures->where('enabled', true)->map(fn ($f) => ['key' => $f->feature_key, 'limit' => $f->limit])->values(),
        ]);

        $usage = [];
        if ($current && $workspace) {
            $counts = [
                'contacts'       => $workspace->contacts()->count(),
                'email_accounts' => $workspace->emailAccounts()->count(),
                'team_members'   => $workspace->members()->count(),
                'workflows'      => $workspace->workflows()->count(),
                'kb_documents'   => KbDocument::where('workspace_id', $wid)->count(),
            ];
            foreach ($counts as $k => $used) {
                $usage[] = ['key' => $k, 'used' => $used, 'limit' => $current->featureLimit($k)];
            }
            $records = UsageRecord::where('workspace_id', $wid)->where('period', now()->format('Y-m'))->get()->keyBy('feature_key');
            foreach (['ai_replies', 'campaigns_per_month', 'storage_mb'] as $k) {
                $usage[] = ['key' => $k, 'used' => (int) ($records->get($k)?->quantity ?? 0), 'limit' => $current->featureLimit($k)];
            }
        }

        $history = Payment::where('workspace_id', $wid)->orderByDesc('created_at')->limit(20)->get()->map(fn ($p) => [
            'id'          => $p->id,
            'amount'      => (float) $p->amount,
            'currency'    => $p->currency,
            'status'      => $p->status,
            'description' => $p->description,
            'gateway'     => $p->gateway_slug,
            'created_at'  => $p->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => [
            'subscription' => $sub ? [
                'status'               => $sub->status,
                'billing_cycle'        => $sub->billing_cycle,
                'trial_ends_at'        => $sub->trial_ends_at?->toIso8601String(),
                'current_period_end'   => $sub->current_period_end?->toIso8601String(),
                'canceled_at'          => $sub->canceled_at?->toIso8601String(),
                'plan'                 => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            ] : null,
            'plans'   => $plans,
            'usage'   => $usage,
            'history' => $history,
        ]]);
    }

    /**
     * POST /auth/web-link {path}  →  {url}
     * One-time (2 min) sign-in link for a whitelisted web path.
     */
    public function webLink(Request $request): JsonResponse
    {
        $d = $request->validate(['path' => ['required', 'string', 'max:300', 'regex:#^/(checkout|settings|integrations|email-oauth)(/[A-Za-z0-9_\-./?=&%]*)?$#']]);
        $token = Str::random(48);
        Cache::put('app_handoff:' . $token, ['user_id' => $request->user()->id, 'path' => $d['path'], 'app' => $request->boolean('app')], now()->addMinutes(2));

        return response()->json(['data' => ['url' => url('/app-handoff/' . $token)]]);
    }
}
