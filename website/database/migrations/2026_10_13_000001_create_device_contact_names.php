<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_contact_names', function (Blueprint $table) {
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone_key', 12);
            $table->string('name');
            $table->primary(['viewer_id', 'phone_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('device_contact_names'); }
};
