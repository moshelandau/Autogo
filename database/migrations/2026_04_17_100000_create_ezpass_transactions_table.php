<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ez_pass_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ez_pass_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag_number')->nullable();
            $table->string('plate')->nullable()->index();
            $table->string('plate_state', 2)->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->string('agency')->nullable();          // "MTA Bridges & Tunnels", "NJTA", etc.
            $table->string('plaza')->nullable();           // toll plaza name
            $table->string('lane')->nullable();
            $table->decimal('amount', 8, 2)->default(0);
            $table->enum('type', ['toll','violation','admin_fee','rebate','other'])->default('toll');
            $table->string('source_file')->nullable();     // CSV file name imported from
            $table->string('external_ref')->nullable()->index(); // EZ Pass transaction id from CSV
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['external_ref', 'posted_at'], 'ezt_uniq_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ez_pass_transactions');
    }
};
