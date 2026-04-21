<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('s3_settings_history', function (Blueprint $table) {
            $table->id();
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->string('access_key')->nullable();
            $table->string('secret_key')->nullable();      // encrypted via casts
            $table->boolean('test_passed')->default(false);
            $table->text('test_message')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s3_settings_history');
    }
};
