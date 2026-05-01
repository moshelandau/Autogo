<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof-of-coverage records for customers. A renter may show a different card
 * each rental (renewals, switches), so we keep history rather than overwriting
 * a single insurance_company / insurance_policy string on the customer row.
 *
 * The card photo lives in storage/public; the `ocr` JSON keeps whatever the
 * Anthropic vision pass extracted (carrier, NAIC, named insured, expiration,
 * coverages) so we can reconcile against typed fields and re-render later
 * without re-running the model.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('carrier')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('naic')->nullable();
            $table->string('named_insured')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('image_path')->nullable();           // photo of card / dec page
            $table->jsonb('ocr')->nullable();                   // raw extraction
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_insurance_policies');
    }
};
