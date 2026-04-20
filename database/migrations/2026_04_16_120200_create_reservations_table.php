<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_class')->nullable(); // requested class
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('return_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->dateTime('pickup_date');
            $table->dateTime('return_date');
            $table->dateTime('actual_pickup_date')->nullable();
            $table->dateTime('actual_return_date')->nullable();
            $table->decimal('daily_rate', 8, 2)->default(0);
            $table->integer('total_days')->default(1);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('addons_total', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('total_refunded', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->decimal('outstanding_balance', 10, 2)->default(0);
            $table->string('status')->default('open'); // open, rental, completed, cancelled, no_show
            $table->integer('odometer_out')->nullable();
            $table->integer('odometer_in')->nullable();
            $table->string('fuel_out')->nullable();
            $table->string('fuel_in')->nullable();
            $table->text('pickup_notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'pickup_date']);
            $table->index(['status', 'return_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('reservations'); }
};
