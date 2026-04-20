<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vin', 17)->unique()->nullable();
            $table->integer('year');
            $table->string('make');
            $table->string('model');
            $table->string('trim')->nullable();
            $table->string('color')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('vehicle_class')->default('car'); // car, suv, minivan, truck
            $table->string('status')->default('available'); // available, rented, maintenance, out_of_service, sold
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('odometer')->default(0);
            $table->string('fuel_level')->nullable(); // full, 3/4, 1/2, 1/4, empty
            $table->decimal('daily_rate', 8, 2)->default(0);
            $table->decimal('weekly_rate', 8, 2)->default(0);
            $table->decimal('monthly_rate', 8, 2)->default(0);
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('vehicles'); }
};
