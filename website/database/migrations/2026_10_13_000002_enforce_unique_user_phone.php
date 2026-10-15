<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preflight before changing data: existing collisions require an administrator's resolution.
        $seen = [];
        foreach (DB::table('users')->whereNotNull('phone')->orderBy('id')->cursor() as $user) {
            $digits = preg_replace('/\D+/', '', $user->phone);
            if ($digits === '') continue;
            if (isset($seen[$digits])) throw new RuntimeException('Duplicate mobile numbers exist on account IDs '.$seen[$digits].' and '.$user->id.'. Run php artisan phones:duplicates to review and resolve them before rerunning this migration.');
            $seen[$digits] = (int) $user->id;
        }
        DB::table('users')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $digits = preg_replace('/\D+/', '', (string) $user->phone);
                DB::table('users')->where('id', $user->id)->update(['phone' => $digits === '' ? null : '+' . $digits]);
            }
        });
        Schema::table('users', fn (Blueprint $table) => $table->unique('phone', 'users_phone_unique'));
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_phone_unique'));
    }
};
