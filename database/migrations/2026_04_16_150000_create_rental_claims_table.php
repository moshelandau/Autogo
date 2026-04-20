<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rental_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('new'); // new, pending_documents, completed, approved
            $table->string('priority')->nullable(); // low, medium, high
            $table->string('brand')->default('high_rental'); // high_rental, mm_car_rental
            // Damage info
            $table->text('damage_description')->nullable();
            $table->date('incident_date')->nullable();
            $table->decimal('damage_amount', 10, 2)->nullable();
            $table->decimal('deductible_amount', 10, 2)->nullable();
            $table->decimal('collected_amount', 10, 2)->default(0);
            // Insurance
            $table->string('insurance_company')->nullable();
            $table->string('insurance_claim_number')->nullable();
            $table->string('insurance_contact')->nullable();
            $table->string('insurance_phone')->nullable();
            // Notes
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('rental_claims'); }
};
