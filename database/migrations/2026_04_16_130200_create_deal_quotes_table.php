<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type'); // lease, finance, one_pay, balloon, cash
            $table->integer('term')->nullable();
            $table->integer('mileage_per_year')->nullable();
            $table->decimal('monthly_payment', 10, 2)->nullable();
            $table->decimal('das', 10, 2)->nullable(); // drive and sign / due at signing
            $table->decimal('sell_price', 10, 2)->nullable();
            $table->decimal('msrp', 10, 2)->nullable();
            $table->decimal('rebates', 10, 2)->default(0);
            $table->decimal('acquisition_fee', 8, 2)->nullable();
            $table->string('acquisition_fee_type')->nullable(); // upfront, capped
            $table->decimal('residual_value', 10, 2)->nullable();
            $table->decimal('money_factor', 10, 6)->nullable();
            $table->decimal('apr', 6, 3)->nullable();
            $table->json('tax_breakdown')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('deal_quotes'); }
};
