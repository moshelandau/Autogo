<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured fields for the leasing workflow points the user spec'd
 * (lead capture, co-signer link, delivery, paperwork tracking, BD pay,
 * plate transfer, insurance status). Each field corresponds to one of
 * the workflow tasks defined in Deal::STAGE_TASKS — staff can either
 * just check the task off, or fill these structured fields for richer
 * tracking and reporting.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            // Lead-stage capture: what kind of car the customer is shopping for.
            // Distinct from the eventual vehicle_make/model/year fields, which
            // describe the actual car matched to the deal.
            // JSONB so the shape can extend (e.g. new style options) without
            // further migrations. Keys: style, budget, miles_per_year,
            // passengers, color, brand.
            $table->jsonb('preferences')->nullable();

            // Application stage: optional co-signer linked as another customer.
            // Reuses the customer infrastructure (phones, license docs, etc.).
            $table->foreignId('co_signer_customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Pending stage: insurance work + plate transfer flag.
            $table->string('insurance_status', 30)->nullable(); // pending, verified, needs_update, n/a
            $table->boolean('plate_transfer')->default(false);

            // Finalize stage: scheduled delivery time.
            $table->timestamp('delivery_scheduled_at')->nullable();

            // Outstanding stage: down at delivery + paperwork tracking number.
            $table->decimal('down_collected_at_delivery', 10, 2)->nullable();
            $table->string('paperwork_tracking_number', 60)->nullable();

            // Complete stage: Bird Dog payment from the dealer (~1 month after).
            $table->date('bd_payment_received_at')->nullable();
            $table->decimal('bd_payment_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['co_signer_customer_id']);
            $table->dropColumn([
                'preferences',
                'co_signer_customer_id',
                'insurance_status',
                'plate_transfer',
                'delivery_scheduled_at',
                'down_collected_at_delivery',
                'paperwork_tracking_number',
                'bd_payment_received_at',
                'bd_payment_amount',
            ]);
        });
    }
};
