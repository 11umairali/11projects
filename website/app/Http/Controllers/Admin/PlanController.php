<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanController extends Controller
{
    /**
     * All resource-limit feature keys (numeric limits, null = unlimited).
     */
    private const LIMIT_KEYS = [
        'email_accounts',
        'ai_replies',
        'contacts',
        'team_members',
        'storage_mb',
        'campaigns_per_month',
        'workflows',
        'kb_documents',
        'kb_file_size_mb',
        'temp_mail_addresses',
        'temp_mail_lifetime_hours',
    ];

    /**
     * All boolean feature keys (enabled / disabled).
     */
    private const TOGGLE_KEYS = [
        'knowledge_base',
        'campaigns',
        'deal_pipeline',
        'analytics',
        'whatsapp',
        'sms',
        'telegram',
        'slack',
        'live_chat',
        'api_access',
        'priority_support',
        'custom_roles',
        'white_label',
        'sso_saml',
        'temp_mail',
        // Premium AI + campaign features added in Phase 2/3.
        // Toggling these on a plan enables them for any workspace
        // subscribed to it (controls visible in /settings/ai and the
        // campaign editor's SMS option respectively).
        'ai_own_key',
        'ai_per_channel',
        'ai_auto_escalation',
        'sms_campaigns',
        // The following are FREE by default and shown in admin UI only
        // for completeness — admins can disable them per-plan if they
        // ever need to fence them off (e.g., a deeply locked-down tier).
        'email_templates',
        'workflow_in_app_notify',
    ];

    /**
     * List all plans with subscriber counts.
     */
    public function configureDefault(Request $request): RedirectResponse
    {
        $request->validate(['default_plan' => 'required|string']);
        \App\Support\BillingPolicy::setDefault($request->input('default_plan'));
        return back()->with('success', 'Default plan updated.');
    }

    public function index(): View
    {
        $plans = Plan::withCount([
            'subscriptions as active_subscriptions_count' => function ($q) {
                $q->where('status', 'active');
            },
            'subscriptions as monthly_sub_count' => function ($q) {
                $q->where('status', 'active')->where('billing_cycle', 'monthly');
            },
            'subscriptions as yearly_sub_count' => function ($q) {
                $q->where('status', 'active')->where('billing_cycle', 'yearly');
            },
        ])
            ->with('planFeatures')
            ->orderBy('sort_order')
            ->get();

        // Calculate MRR per plan using pre-loaded counts (no extra queries)
        $plans->each(function ($plan) {
            $monthlyMrr = $plan->monthly_sub_count * $plan->monthly_price;
            $yearlyMrr = $plan->yearly_sub_count * ($plan->yearly_price / 12);
            $plan->plan_mrr = round($monthlyMrr + $yearlyMrr, 2);
        });

        return view('admin.plans.index', compact('plans'));
    }

    /**
     * Show the create plan form.
     */
    public function create(): View
    {
        return view('admin.plans.create');
    }

    /**
     * Store a newly created plan with resource limits, feature toggles, and pricing features.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|alpha_dash|unique:plans|not_in:__free_unlimited__',
            'description' => 'nullable|string|max:1000',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:500',
            'limits' => 'nullable|array',
            'limits.*' => 'nullable|integer|min:0',
            'limits_unlimited' => 'nullable|array',
            'toggles' => 'nullable|array',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $plan = Plan::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'monthly_price' => $validated['monthly_price'],
                'yearly_price' => $validated['yearly_price'],
                'is_active' => $request->boolean('is_active', true),
                'is_popular' => $request->boolean('is_popular', false),
                'sort_order' => $validated['sort_order'] ?? 0,
                'features' => array_values(array_filter($validated['features'] ?? [])),
            ]);

            $this->syncPlanFeatures($plan, $request);
        });

        if ($request->input('action') === 'save_and_create') {
            return redirect()->route('admin.plans.create')
                ->with('success', 'Plan created successfully. Create another one.');
        }

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    /**
     * Show the edit form for a plan.
     */
    public function edit(int $id): View
    {
        $plan = Plan::with('planFeatures')->findOrFail($id);

        return view('admin.plans.edit', compact('plan'));
    }

    /**
     * Update a plan's details, resource limits, feature toggles, and pricing features.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'sort_order' => 'required|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:500',
            'limits' => 'nullable|array',
            'limits.*' => 'nullable|integer|min:0',
            'limits_unlimited' => 'nullable|array',
            'toggles' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if (!$request->boolean('is_active', true) && \App\Support\BillingPolicy::defaultPlan()?->id === $plan->id) {
            return back()->withErrors(['is_active' => 'Choose another default plan before disabling this plan.'])->withInput();
        }

        // Block price changes if there are active subscriptions on this plan
        $activeSubs = $plan->subscriptions()->where('status', 'active')->count();
        $priceChanged = (float) $plan->monthly_price !== (float) $request->input('monthly_price')
            || (float) $plan->yearly_price !== (float) $request->input('yearly_price');

        if ($activeSubs > 0 && $priceChanged) {
            return redirect()->back()
                ->with('error', "Cannot change pricing: {$activeSubs} active subscription(s) on this plan. Create a new plan instead, or cancel existing subscriptions first.")
                ->withInput();
        }

        DB::transaction(function () use ($plan, $request) {
            $plan->update([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'monthly_price' => $request->input('monthly_price'),
                'yearly_price' => $request->input('yearly_price'),
                'is_active' => $request->boolean('is_active', true),
                'is_popular' => $request->boolean('is_popular', false),
                'sort_order' => $request->input('sort_order'),
                'features' => array_values(array_filter($request->input('features', []))),
            ]);

            $this->syncPlanFeatures($plan, $request);
        });

        return redirect()->route('admin.plans.index')
            ->with('success', "Plan '{$plan->name}' updated successfully.");
    }

    /**
     * Display a single plan with feature limits and recent subscribers.
     */
    public function show(int $id): View
    {
        $plan = Plan::with(['planFeatures', 'subscriptions' => fn($q) => $q->where('status', 'active')->with('workspace.members')->latest()->limit(10)])
            ->withCount(['subscriptions as active_subscriptions_count' => fn($q) => $q->where('status', 'active')])
            ->findOrFail($id);

        return view('admin.plans.show', compact('plan'));
    }

    /**
     * Delete a plan only if it has no active subscriptions.
     */
    public function destroy(int $id): RedirectResponse
    {
        $plan = Plan::withCount(['subscriptions as active_subscriptions_count' => fn($q) => $q->where('status', 'active')])
            ->findOrFail($id);

        if ($plan->active_subscriptions_count > 0 || \App\Support\BillingPolicy::defaultPlan()?->id === $plan->id) {
            return redirect()->route('admin.plans.show', $plan->id)
                ->with('error', "Cannot delete plan '{$plan->name}': it is the default plan or has active subscriptions. Choose another default and cancel or migrate subscribers first.");
        }

        DB::transaction(function () use ($plan) {
            $plan->planFeatures()->delete();
            $plan->delete();
        });

        return redirect()->route('admin.plans.index')
            ->with('success', "Plan '{$plan->name}' has been deleted.");
    }

    /**
     * Import plans from a CSV file.
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

            $name = $data['Name'] ?? $data['name'] ?? null;
            if (!$name) continue;

            Plan::firstOrCreate(['name' => $name], [
                'slug' => Str::slug($name),
                'monthly_price' => $data['Monthly Price'] ?? $data['monthly_price'] ?? 0,
                'yearly_price' => $data['Yearly Price'] ?? $data['yearly_price'] ?? 0,
                'description' => $data['Description'] ?? $data['description'] ?? '',
                'is_active' => true,
            ]);
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "$imported plans imported.");
    }

    /**
     * Export all plans as a CSV download.
     * Supports ?template=1 to return an empty CSV with headers only.
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = ['Name', 'Slug', 'Monthly Price', 'Yearly Price', 'Is Active', 'Is Popular', 'Sort Order', 'Description'];

        if ($request->has('template')) {
            return response()->streamDownload(function () use ($headers) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);
                fclose($handle);
            }, 'plans-template.csv', ['Content-Type' => 'text/csv']);
        }

        $plans = Plan::orderBy('sort_order')->get();

        return response()->streamDownload(function () use ($plans, $headers) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers);

            foreach ($plans as $plan) {
                fputcsv($handle, [
                    $plan->name,
                    $plan->slug,
                    $plan->monthly_price,
                    $plan->yearly_price,
                    $plan->is_active ? 'Yes' : 'No',
                    $plan->is_popular ? 'Yes' : 'No',
                    $plan->sort_order,
                    $plan->description,
                ]);
            }

            fclose($handle);
        }, 'plans-export-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Bulk delete plans (only those with no active subscriptions).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $plans = Plan::withCount(['subscriptions as active_subscriptions_count' => fn($q) => $q->where('status', 'active')])
            ->whereIn('id', $request->ids)
            ->get();

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($plans, &$deleted, &$skipped) {
            foreach ($plans as $plan) {
                if ($plan->active_subscriptions_count > 0 || \App\Support\BillingPolicy::defaultPlan()?->id === $plan->id) {
                    $skipped++;
                    continue;
                }
                $plan->planFeatures()->delete();
                $plan->delete();
                $deleted++;
            }
        });

        $message = "{$deleted} plans deleted.";
        if ($skipped > 0) {
            $message .= " {$skipped} plans skipped (have active subscriptions).";
        }

        return back()->with('success', $message);
    }

    /**
     * Sync plan features from the structured form data.
     *
     * Handles two categories:
     * 1. Resource limits (limits[] + limits_unlimited[]) -- numeric caps or null for unlimited
     * 2. Feature toggles (toggles[]) -- boolean on/off for integrations & capabilities
     */
    private function syncPlanFeatures(Plan $plan, Request $request): void
    {
        // Delete all existing plan features and re-create from form data
        $plan->planFeatures()->delete();

        $limits = $request->input('limits', []);
        $unlimited = $request->input('limits_unlimited', []);
        $toggles = $request->input('toggles', []);

        // Resource limits
        foreach (self::LIMIT_KEYS as $key) {
            $isUnlimited = ($unlimited[$key] ?? '0') === '1';
            $value = $limits[$key] ?? null;

            // If the limit field is empty and not unlimited, store as enabled=true, limit=0
            // This way every key always has a record -- makes querying simpler
            $plan->planFeatures()->create([
                'feature_key' => $key,
                'enabled' => true,
                'limit' => $isUnlimited ? null : (int) ($value ?? 0),
            ]);
        }

        // Boolean toggles
        foreach (self::TOGGLE_KEYS as $key) {
            $enabled = ($toggles[$key] ?? '0') === '1';

            $plan->planFeatures()->create([
                'feature_key' => $key,
                'enabled' => $enabled,
                'limit' => null,
            ]);
        }
    }
}
