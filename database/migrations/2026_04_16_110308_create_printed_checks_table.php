<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('printed_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_account_id')->constrained();
            $table->integer('check_number');
            $table->unsignedBigInteger('vendor_payment_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payee_name');
            $table->string('memo')->nullable();
            $table->date('check_date');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('printed_checks'); }
};
