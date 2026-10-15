<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compose checks the 'emails_per_month' plan feature before queuing a send.
 * No plan defined that feature, and Plan::hasFeature() treats a missing row
 * as "disabled" for the Free plan — so every Free / no-subscription user was
 * told they'd hit their monthly limit on their very first email.
 *
 * Paid plans are unaffected (a missing row means "enabled, unlimited" for
 * them), so only Free needs a row. 100 emails/month is a starting point —
 * change it any time in the admin Plans screen (or set `limit` to NULL for
 * unlimited).
 */
return new class extends Migration
{
    public function up(): void
    {
        $free = DB::table('plans')->where('slug', 'free')->first();

        if (! $free) {
            return;
        }

        $exists = DB::table('plan_features')
            ->where('plan_id', $free->id)
            ->where('feature_key', 'emails_per_month')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('plan_features')->insert([
            'plan_id' => $free->id,
            'feature_key' => 'emails_per_month',
            'enabled' => 1,
            'limit' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $free = DB::table('plans')->where('slug', 'free')->first();

        if ($free) {
            DB::table('plan_features')
                ->where('plan_id', $free->id)
                ->where('feature_key', 'emails_per_month')
                ->delete();
        }
    }
};
