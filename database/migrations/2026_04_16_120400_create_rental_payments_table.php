<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('payment_method'); // credit_card, cash, check, transfer
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->string('status')->default('approved'); // pending, approved, declined, refunded
            $table->string('type')->default('payment'); // payment, deposit, refund
            $table->json('sola_transaction_data')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('rental_payments'); }
};
