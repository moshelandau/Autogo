<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lease_application_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();          // E.164 of the customer's phone
            $table->string('flow')->default('lease');  // lease | rental
            $table->string('current_step');            // see LeaseApplicationBot::STEPS_*
            $table->jsonb('collected')->nullable();    // accumulated answers
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('aborted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_application_sessions');
    }
};
