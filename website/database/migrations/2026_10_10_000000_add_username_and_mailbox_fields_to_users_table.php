<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: the existing admin account (real Gmail address) has
            // no username and is left exactly as it is.
            $table->string('username', 30)->nullable()->unique()->after('email');
            $table->text('recovery_phrase_hash')->nullable()->after('password');
            $table->string('signup_ip', 45)->nullable()->after('recovery_phrase_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'recovery_phrase_hash', 'signup_ip']);
        });
    }
};
