<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('description');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('coupon_code');
            $table->decimal('original_amount', 10, 2)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'discount_amount', 'original_amount']);
        });
    }
};
