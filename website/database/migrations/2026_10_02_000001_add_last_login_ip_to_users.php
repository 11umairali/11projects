<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'last_login_ip')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_login_ip', 45)->nullable()->after('signup_ip');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_login_ip')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_login_ip'));
        }
    }
};
