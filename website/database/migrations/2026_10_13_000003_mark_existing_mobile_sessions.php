<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('personal_access_tokens')->whereNull('expires_at')
            ->where(fn ($q) => $q->where('name', 'mobile')->orWhere('name', 'like', '% mobile app')->orWhereIn('name', ['social:google', 'social:facebook', 'social:apple']))
            ->orderBy('id')->chunkById(500, function ($tokens) {
                foreach ($tokens as $token) {
                    $abilities = json_decode($token->abilities, true) ?: [];
                    if (!in_array('mobile-session', $abilities, true)) {
                        $abilities[] = 'mobile-session';
                        DB::table('personal_access_tokens')->where('id', $token->id)->update(['abilities' => json_encode($abilities)]);
                    }
                }
            });
    }
    public function down(): void
    {
        // Tokens remain usable; rolling back the application restores its expiry policy.
    }
};
