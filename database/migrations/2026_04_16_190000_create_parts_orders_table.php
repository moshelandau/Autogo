<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parts_orders', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_description'); // e.g. "2023 Pilot Touring", "Honda Accord 7014"
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, ordered, received, installed, out
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('parts_list')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('parts_orders'); }
};
