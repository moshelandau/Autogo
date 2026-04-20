<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('status')->default('new'); // new, filed, in_progress, completed
            // Accident info
            $table->text('story')->nullable()->comment('What happened - accident description');
            $table->date('accident_date')->nullable();
            $table->string('accident_location')->nullable();
            $table->string('customer_phone')->nullable();
            // Adjuster info
            $table->string('adjuster_name')->nullable();
            $table->string('adjuster_phone')->nullable();
            $table->string('adjuster_email')->nullable();
            // Vehicle Appraiser info
            $table->string('appraiser_name')->nullable();
            $table->string('appraiser_phone')->nullable();
            $table->string('appraiser_email')->nullable();
            // Vehicle info
            $table->string('vehicle_year')->nullable();
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_vin')->nullable();
            $table->string('vehicle_plate')->nullable();
            // Financial
            $table->decimal('estimate_amount', 10, 2)->nullable();
            $table->decimal('supplement_amount', 10, 2)->nullable();
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('towing_amount', 10, 2)->nullable();
            $table->decimal('rental_amount', 10, 2)->nullable();
            // Notes
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
