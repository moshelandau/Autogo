<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('check_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('logo_path')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('account_number')->nullable();
            $table->integer('check_start_number')->default(1001);
            $table->integer('next_check_number')->default(1001);
            $table->string('account_holder_name')->nullable();
            $table->text('account_holder_address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('check_accounts'); }
};
