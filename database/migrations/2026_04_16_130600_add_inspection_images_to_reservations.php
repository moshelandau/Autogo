<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // pickup, return
            $table->string('image_path');
            $table->string('area')->nullable(); // front, rear, left, right, interior, damage
            $table->text('notes')->nullable();
            $table->json('ai_analysis')->nullable(); // auto-analyze results
            $table->boolean('has_damage')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reservation_inspections'); }
};
