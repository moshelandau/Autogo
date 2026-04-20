<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Towing ────────────────────────────────────────────
        Schema::create('tow_trucks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('license_plate')->nullable();
            $table->string('type')->nullable(); // flatbed, wheel-lift, heavy
            $table->unsignedSmallInteger('capacity_vehicles')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tow_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('cdl_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tow_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tow_truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tow_driver_id')->nullable()->constrained()->nullOnDelete();

            $table->string('caller_name')->nullable();
            $table->string('caller_phone')->nullable();
            $table->string('insurance_company')->nullable();
            $table->string('reference_number')->nullable(); // PO / claim # from insurer

            $table->string('vehicle_year')->nullable();
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_vin')->nullable();

            $table->string('pickup_address');
            $table->string('pickup_city')->nullable();
            $table->string('pickup_state', 2)->nullable();
            $table->string('pickup_zip')->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();

            $table->string('dropoff_address');
            $table->string('dropoff_city')->nullable();
            $table->string('dropoff_state', 2)->nullable();
            $table->string('dropoff_zip')->nullable();

            $table->enum('status', ['pending', 'dispatched', 'en_route', 'on_scene', 'in_transit', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('reason', ['accident', 'breakdown', 'repo', 'illegal_parking', 'transport', 'other'])->default('other');

            $table->decimal('quoted_amount', 10, 2)->nullable();
            $table->decimal('billed_amount', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('on_scene_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index('requested_at');
        });

        // ── Bodyshop ──────────────────────────────────────────
        Schema::create('bodyshop_workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('role', ['tech', 'painter', 'detailer', 'estimator', 'manager', 'helper'])->default('tech');
            $table->string('color')->default('#6366f1'); // for floor-view chip color
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->date('hire_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bodyshop_lifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Lift 1", "Bay A", "Booth"
            $table->enum('type', ['lift', 'bay', 'spray_booth', 'frame_machine', 'detail_bay'])->default('lift');
            $table->unsignedSmallInteger('position')->default(0); // sort order on the floor view
            $table->string('color')->default('#0ea5e9');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bodyshop_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodyshop_lift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bodyshop_worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('vehicle_label')->nullable(); // e.g. "2024 Honda Civic — ABC123"
            $table->string('vehicle_plate')->nullable();
            $table->string('repair_phase')->nullable(); // disassembly, body, paint, reassembly, detail, ready
            $table->enum('status', ['scheduled', 'in_progress', 'paused', 'completed'])->default('in_progress');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->date('estimated_completion')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bodyshop_lift_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bodyshop_slots');
        Schema::dropIfExists('bodyshop_lifts');
        Schema::dropIfExists('bodyshop_workers');
        Schema::dropIfExists('tow_jobs');
        Schema::dropIfExists('tow_drivers');
        Schema::dropIfExists('tow_trucks');
    }
};
