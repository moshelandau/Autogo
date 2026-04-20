<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label'); // toll, parking, fuel, damage, fine, other
            $table->decimal('amount', 10, 2);
            $table->date('charge_date');
            $table->string('provider')->nullable(); // NYSTA, MTABAT, EPass
            $table->string('reference')->nullable();
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, billed_to_customer
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('external_charges'); }
};
