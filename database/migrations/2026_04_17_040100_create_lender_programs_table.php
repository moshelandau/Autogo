<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lender_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained();
            $table->string('program_type')->default('lease'); // lease, finance
            // Vehicle scope
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('trim')->nullable();
            // Lease terms
            $table->integer('term')->nullable(); // months: 24, 36, 39, 42, 48
            $table->integer('annual_mileage')->nullable(); // 7500, 10000, 12000, 15000
            // The KEY values
            $table->decimal('residual_pct', 5, 2)->nullable(); // 60.50 = 60.5%
            $table->decimal('money_factor', 10, 6)->nullable(); // 0.00185
            $table->decimal('apr', 6, 3)->nullable(); // 5.99 for finance
            // Fees
            $table->decimal('acquisition_fee', 8, 2)->nullable();
            $table->decimal('disposition_fee', 8, 2)->nullable();
            // Constraints
            $table->integer('min_credit_score')->nullable();
            $table->string('credit_tier')->nullable(); // tier_1, etc.
            $table->decimal('max_msrp', 10, 2)->nullable();
            // Validity
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('manual'); // manual, autofi, marketscan
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lender_id', 'make', 'model', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
        });
    }
    public function down(): void { Schema::dropIfExists('lender_programs'); }
};
