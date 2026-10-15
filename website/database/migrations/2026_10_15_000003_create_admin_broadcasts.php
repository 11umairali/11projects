<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('admin_broadcasts', function (Blueprint $t) {
            $t->id(); $t->foreignId('created_by')->constrained('users');
            $t->string('category', 24); $t->string('title', 160); $t->text('body');
            $t->string('button_label', 60)->nullable(); $t->string('action_url', 2048)->nullable();
            $t->string('accent', 7)->default('#2563EB'); $t->boolean('push'); $t->boolean('email');
            $t->string('status', 24)->default('draft'); $t->unsignedInteger('audience_count')->default(0); $t->timestamps();
        });
        Schema::create('admin_broadcast_deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('broadcast_id')->constrained('admin_broadcasts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('push_status', 24)->default('pending'); $t->string('email_status', 24)->default('pending');
            $t->unique(['broadcast_id', 'user_id']); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('admin_broadcast_deliveries'); Schema::dropIfExists('admin_broadcasts'); }
};
