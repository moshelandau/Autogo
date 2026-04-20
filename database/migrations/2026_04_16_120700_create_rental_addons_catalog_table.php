<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rental_addons_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('equipment'); // equipment, protection, penalty, service
            $table->string('charge_type')->default('per_day'); // per_day, per_rental, one_time
            $table->decimal('rate', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('rental_addons_catalog'); }
};
