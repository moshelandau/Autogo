<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip')->nullable();
            $table->string('makes_carried')->nullable(); // "Honda, Toyota, Hyundai"
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('insurers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('claims_phone')->nullable();
            $table->string('claims_email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add dealer/insurer references to deals
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('lender_id')->constrained()->nullOnDelete();
            $table->foreignId('insurer_id')->nullable()->after('dealer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dealer_id');
            $table->dropConstrainedForeignId('insurer_id');
        });
        Schema::dropIfExists('insurers');
        Schema::dropIfExists('dealers');
    }
};
