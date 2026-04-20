<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_line_id')->constrained();
            $table->boolean('is_cleared')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bank_reconciliation_items'); }
};
