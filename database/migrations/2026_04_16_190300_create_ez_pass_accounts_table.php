<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ez_pass_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('account_number')->nullable();
            $table->string('tag_number')->nullable();
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->decimal('balance', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ez_pass_accounts'); }
};
