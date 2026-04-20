<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_pulls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // soft, hard
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->string('provider')->default('700credit'); // 700credit, manual
            // Customer info at time of pull
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('ssn_last4')->nullable(); // only stored for hard pulls
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            // Results
            $table->integer('credit_score')->nullable();
            $table->string('credit_score_model')->nullable(); // FICO 8, Vantage, etc.
            $table->string('credit_tier')->nullable(); // tier_1, tier_2, tier_3, tier_4
            $table->string('bureau')->nullable(); // experian, equifax, transunion, all
            $table->json('full_report')->nullable(); // raw API response
            $table->string('report_pdf_path')->nullable();
            // Compliance / audit
            $table->foreignId('pulled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('permissible_purpose')->nullable();
            $table->string('ip_address')->nullable();
            $table->boolean('customer_consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // soft pulls expire after 30 days for re-use
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'created_at']);
            $table->index(['deal_id', 'type']);
        });
    }
    public function down(): void { Schema::dropIfExists('credit_pulls'); }
};
