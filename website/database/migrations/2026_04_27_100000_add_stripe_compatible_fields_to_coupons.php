<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Convert `type` enum to string so it can store percent_off / amount_off / etc.
        DB::statement("ALTER TABLE coupons MODIFY type VARCHAR(32) NOT NULL DEFAULT 'percent_off'");

        // Relax legacy NOT-NULL columns so inserts that only target the new columns succeed.
        DB::statement("ALTER TABLE coupons MODIFY value DECIMAL(10,2) NULL");
        DB::statement("ALTER TABLE coupons MODIFY max_uses INT UNSIGNED NULL");
        DB::statement("ALTER TABLE coupons MODIFY times_used INT UNSIGNED NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE coupons MODIFY applicable_plans JSON NULL");

        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'percent_off')) {
                $table->decimal('percent_off', 5, 2)->nullable()->after('type');
            }
            if (!Schema::hasColumn('coupons', 'amount_off')) {
                $table->decimal('amount_off', 10, 2)->nullable()->after('percent_off');
            }
            if (!Schema::hasColumn('coupons', 'duration')) {
                $table->string('duration', 16)->default('once')->after('currency');
            }
            if (!Schema::hasColumn('coupons', 'duration_in_months')) {
                $table->unsignedInteger('duration_in_months')->nullable()->after('duration');
            }
            if (!Schema::hasColumn('coupons', 'max_redemptions')) {
                $table->unsignedInteger('max_redemptions')->nullable()->after('duration_in_months');
            }
            if (!Schema::hasColumn('coupons', 'times_redeemed')) {
                $table->unsignedInteger('times_redeemed')->default(0)->after('max_redemptions');
            }
        });

        // Backfill new columns from legacy ones so existing rows keep working.
        DB::table('coupons')->whereNotNull('value')->orderBy('id')->each(function ($row) {
            $isPercent = in_array($row->type, ['percent', 'percent_off'], true);
            DB::table('coupons')->where('id', $row->id)->update([
                'type' => $isPercent ? 'percent_off' : 'amount_off',
                'percent_off' => $isPercent ? $row->value : null,
                'amount_off' => $isPercent ? null : $row->value,
                'max_redemptions' => $row->max_uses ?? null,
                'times_redeemed' => $row->times_used ?? 0,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $columns = ['percent_off', 'amount_off', 'duration', 'duration_in_months', 'max_redemptions', 'times_redeemed'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('coupons', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::statement("ALTER TABLE coupons MODIFY type ENUM('percent','fixed') NOT NULL DEFAULT 'percent'");
        DB::statement("ALTER TABLE coupons MODIFY value DECIMAL(10,2) NOT NULL");
    }
};
