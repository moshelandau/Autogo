<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permission_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permission_type_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_type_id')->constrained()->cascadeOnDelete();
            $table->string('page_key');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['permission_type_id', 'page_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('permission_type_pages');
        Schema::dropIfExists('permission_types');
    }
};
