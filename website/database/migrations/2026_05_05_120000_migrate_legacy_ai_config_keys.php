<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The admin "AI Config" settings tab was removed in favor of the
 * dedicated /admin/ai-providers page (single source of truth — admins
 * were getting confused by two screens that did the same thing).
 *
 * The old tab wrote 4 system_settings keys that nothing in the codebase
 * actually read — the AIManager always read `ai_default_provider` and
 * `ai_default_model` (different underscore order), which is what the
 * surviving /admin/ai-providers page writes.
 *
 * For installs where an admin had been using only the old AI Config tab,
 * their stored values would be silently abandoned. This migration copies
 * the legacy keys onto the active keys ONCE, but only if the active key
 * is empty (i.e. don't overwrite a real /admin/ai-providers selection).
 * Then it deletes the dead legacy keys to keep the table tidy.
 */
return new class extends Migration {
    public function up(): void
    {
        $get = fn (string $k): ?string => optional(
            DB::table('system_settings')->where('key', $k)->first()
        )->value;

        $set = function (string $k, string $v): void {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $k],
                ['value' => $v, 'updated_at' => now()]
            );
        };

        // Provider — copy default_ai_provider → ai_default_provider if empty.
        $legacyProvider = $get('default_ai_provider');
        $activeProvider = $get('ai_default_provider');
        if (!empty($legacyProvider) && empty($activeProvider)) {
            $set('ai_default_provider', $legacyProvider);
        }

        // Model — copy default_ai_model → ai_default_model if empty.
        $legacyModel = $get('default_ai_model');
        $activeModel = $get('ai_default_model');
        if (!empty($legacyModel) && empty($activeModel)) {
            $set('ai_default_model', $legacyModel);
        }

        // Drop the dead legacy keys (no code reads them anymore).
        DB::table('system_settings')
            ->whereIn('key', [
                'default_ai_provider',
                'default_ai_model',
                'ai_temperature',
                'ai_max_tokens',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Non-reversible — the legacy keys were dead weight; restoring
        // them would just confuse a future admin who ran `migrate:rollback`.
    }
};
