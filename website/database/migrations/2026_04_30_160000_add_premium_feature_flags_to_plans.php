<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds plan feature flags for the premium capabilities introduced in
 * Phase 2 / Phase 3:
 *
 *   ai_own_key            — User can plug in their own LLM API key.
 *                            Free/Starter: must use platform keys (admin-paid).
 *                            Pro/Enterprise: can use their own key.
 *   ai_per_channel        — Per-channel AI behavior cards (custom prompt,
 *                            send mode, escalation, filters per channel).
 *   ai_auto_escalation    — Auto hand-off to human when AI confidence is low
 *                            (assigns conversation, tags, notifies assignee).
 *   sms_campaigns         — Bulk SMS campaign module (separate from
 *                            inbox SMS, which is gated by the existing 'sms'
 *                            feature flag).
 *   email_templates       — Pick saved email templates inside the workflow
 *                            send_email action (Phase 1).
 *   workflow_in_app_notify — Workflow "Send Notification" action with the
 *                            in_app channel + bell-icon delivery (Phase 2).
 *
 * Tier mapping (free → starter → pro → enterprise):
 *   ai_own_key             0 / 0 / 1 / 1
 *   ai_per_channel         0 / 0 / 1 / 1
 *   ai_auto_escalation     0 / 0 / 1 / 1
 *   sms_campaigns          0 / 0 / 1 / 1
 *   email_templates        0 / 1 / 1 / 1
 *   workflow_in_app_notify 0 / 1 / 1 / 1
 *
 * Idempotent: skips rows that already exist for a given plan/feature pair.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        // Resolve existing plans by slug — DB might already have rows added
        // by an admin so we don't want to depend on hard-coded ids.
        $plans = DB::table('plans')->get(['id', 'slug', 'name']);

        $matrix = [
            'ai_own_key' => [
                'free' => 0, 'starter' => 0, 'pro' => 1, 'enterprise' => 1,
            ],
            'ai_per_channel' => [
                'free' => 0, 'starter' => 0, 'pro' => 1, 'enterprise' => 1,
            ],
            'ai_auto_escalation' => [
                'free' => 0, 'starter' => 0, 'pro' => 1, 'enterprise' => 1,
            ],
            'sms_campaigns' => [
                'free' => 0, 'starter' => 0, 'pro' => 1, 'enterprise' => 1,
            ],
            // Workflow builder features (email templates inside the
            // send_email action, and the in-app channel for the
            // send_notification action) are FREE for all plans — they
            // are part of the core product, not a paid upcharge.
            'email_templates' => [
                'free' => 1, 'starter' => 1, 'pro' => 1, 'enterprise' => 1,
            ],
            'workflow_in_app_notify' => [
                'free' => 1, 'starter' => 1, 'pro' => 1, 'enterprise' => 1,
            ],
        ];

        foreach ($plans as $plan) {
            $slug = strtolower($plan->slug ?: $plan->name);
            // Map any unrecognized plan to "starter" defaults — safe baseline.
            $tier = match (true) {
                str_contains($slug, 'free') => 'free',
                str_contains($slug, 'enterprise') || str_contains($slug, 'business') => 'enterprise',
                str_contains($slug, 'pro') || str_contains($slug, 'premium') => 'pro',
                default => 'starter',
            };

            foreach ($matrix as $featureKey => $tiers) {
                $enabled = $tiers[$tier] ?? 0;

                $exists = DB::table('plan_features')
                    ->where('plan_id', $plan->id)
                    ->where('feature_key', $featureKey)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('plan_features')->insert([
                    'plan_id' => $plan->id,
                    'feature_key' => $featureKey,
                    'enabled' => $enabled,
                    'limit' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('plan_features')->whereIn('feature_key', [
            'ai_own_key',
            'ai_per_channel',
            'ai_auto_escalation',
            'sms_campaigns',
            'email_templates',
            'workflow_in_app_notify',
        ])->delete();
    }
};
