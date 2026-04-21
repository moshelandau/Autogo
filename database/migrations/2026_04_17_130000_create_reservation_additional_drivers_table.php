<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional drivers on a reservation.
 * Main renter is always legally responsible (set on reservations.customer_id),
 * but for OPERATIONAL contact (EZPass, parking tickets, day-to-day issues)
 * we may prefer to call/text the additional driver first.
 *
 * is_primary_contact = "try this driver first for ops messages"
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_additional_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('dl_number')->nullable();
            $table->string('dl_state', 2)->nullable();
            $table->date('dl_expiration')->nullable();
            $table->string('dl_image_path')->nullable();          // uploaded scan
            $table->boolean('is_primary_contact')->default(false); // try first for ops messages
            // Optional CC for this driver (charges still default to main renter)
            $table->string('cc_brand')->nullable();
            $table->string('cc_last4', 4)->nullable();
            $table->string('cc_exp', 7)->nullable();
            $table->string('cc_token')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_additional_drivers');
    }
};
