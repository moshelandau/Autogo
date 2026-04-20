<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('per_rental'); // per_day, per_rental, one_time
            $table->decimal('rate', 8, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reservation_addons'); }
};
