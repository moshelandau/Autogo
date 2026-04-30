<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Return — captures the customer's outgoing trade-in or
 * lease-return vehicle (xDeskPro deal page → Vehicle Return tab).
 *
 * Distinct from `deals.trade_*` snapshot fields: those are quick
 * trade-allowance / payoff numbers used in the lease calculator;
 * this table holds the structured details a dealer needs (VIN,
 * year/make/model, condition, mileage, lienholder/payoff, plate).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->enum('return_type', ['trade_in', 'lease_return'])->default('trade_in');
            $table->string('vin', 17)->nullable();
            $table->integer('year')->nullable();
            $table->string('make', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('trim', 64)->nullable();
            $table->string('color', 32)->nullable();
            $table->integer('odometer')->nullable();
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor'])->nullable();
            $table->decimal('payoff_amount', 12, 2)->nullable();
            $table->decimal('allowance', 12, 2)->nullable(); // what we credit them
            $table->decimal('acv', 12, 2)->nullable();        // actual cash value (auction)
            $table->string('payoff_to')->nullable();          // bank/leasing co. name
            $table->date('payoff_good_through')->nullable();
            $table->string('current_plate')->nullable();
            $table->boolean('plate_transfer')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('deal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_returns');
    }
};
