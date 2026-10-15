<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('conversations', 'snoozed_until')) {
            return;
        }
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('snoozed_until')->nullable()->after('status');
            $table->index(['workspace_id', 'snoozed_until'], 'conversations_ws_snoozed_index');
        });
    }
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_ws_snoozed_index');
            $table->dropColumn('snoozed_until');
        });
    }
};
