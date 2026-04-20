<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('payee')->nullable();
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->text('description')->nullable();
            $table->string('department')->nullable()->comment('rental, leasing, bodyshop, insurance, general');
            $table->foreignId('source_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('check_to_print')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('expenses'); }
};
