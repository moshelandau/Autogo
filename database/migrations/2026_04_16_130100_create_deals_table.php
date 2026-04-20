<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->integer('deal_number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            // Vehicle info
            $table->string('vehicle_vin', 17)->nullable();
            $table->integer('vehicle_year')->nullable();
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_trim')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->integer('vehicle_odometer')->nullable();
            // Deal type & stage
            $table->string('payment_type')->default('lease'); // lease, finance, one_pay, balloon, cash
            $table->string('stage')->default('lead'); // lead, quote, application, submission, pending, finalize, outstanding, complete, lost
            $table->string('priority')->default('low'); // low, medium, high
            // Financial
            $table->decimal('msrp', 10, 2)->nullable();
            $table->decimal('invoice_price', 10, 2)->nullable();
            $table->decimal('sell_price', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('profit', 10, 2)->nullable();
            $table->decimal('monthly_payment', 10, 2)->nullable();
            $table->integer('term')->nullable(); // months
            $table->integer('mileage_per_year')->nullable();
            $table->decimal('drive_off', 10, 2)->nullable();
            // Trade
            $table->decimal('trade_allowance', 10, 2)->nullable();
            $table->decimal('trade_acv', 10, 2)->nullable();
            $table->decimal('trade_payoff', 10, 2)->nullable();
            $table->boolean('trade_is_leased')->default(false);
            // Customer
            $table->integer('credit_score')->nullable();
            $table->string('customer_zip')->nullable();
            // Lender
            $table->foreignId('lender_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lender_status')->nullable(); // submitted, approved, declined, conditional
            $table->text('lender_notes')->nullable();
            // Meta
            $table->text('notes')->nullable();
            $table->timestamp('deal_start_date')->nullable();
            $table->timestamp('deal_expiration_date')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stage', 'created_at']);
            $table->index(['salesperson_id', 'stage']);
        });
    }
    public function down(): void { Schema::dropIfExists('deals'); }
};
