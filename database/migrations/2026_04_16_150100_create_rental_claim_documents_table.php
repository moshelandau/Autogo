<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rental_claim_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_claim_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('document'); // document, photo, police_report, insurance_card, estimate
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('rental_claim_documents'); }
};
