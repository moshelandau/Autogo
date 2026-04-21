<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle violations: parking, red-light/speed/bus-lane cameras, school-bus camera,
 * toll-evasion, registration violations, etc. — tracked centrally so we can
 * auto-bill the renter who had the vehicle on the violation date.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('plate')->nullable()->index();
            $table->string('plate_state', 2)->nullable();

            // Type + jurisdiction (school bus and camera violations come from many places)
            $table->enum('type', [
                'parking', 'red_light_camera', 'speed_camera', 'bus_lane_camera',
                'school_bus_camera', 'toll_evasion', 'registration', 'inspection',
                'moving_violation', 'other',
            ])->index();
            $table->enum('jurisdiction', ['NY','NJ','CT','PA','MA','MD','VA','DC','OTHER'])->default('NY');
            $table->string('issuing_agency')->nullable();   // "NYC DOF", "NYC DOT", "NYPD", "NYS DMV", "School District XYZ"

            // Identifiers
            $table->string('summons_number')->nullable()->index();
            $table->string('citation_number')->nullable();
            $table->string('issue_number')->nullable();     // catch-all

            // Dates + location
            $table->timestamp('issued_at')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->string('location')->nullable();         // "5th Ave & 42nd St" etc.
            $table->string('borough_or_county')->nullable();

            // Amounts
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);   // our pass-through admin fee
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('total_due', 10, 2)->default(0);   // fine + late + admin - paid

            $table->enum('status', [
                'new','received','renter_notified','renter_billed','paid_by_renter',
                'paid_by_us','disputed','dismissed',
            ])->default('new');

            // Evidence
            $table->string('photo_path')->nullable();
            $table->string('document_path')->nullable();    // PDF of the ticket if we have it
            $table->json('evidence')->nullable();            // array of additional photo paths

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_violations');
    }
};
