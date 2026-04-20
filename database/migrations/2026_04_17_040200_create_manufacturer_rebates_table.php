<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manufacturer_rebates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Honda Loyalty Bonus"
            $table->string('rebate_type'); // loyalty, conquest, college_grad, military, first_responder, lease_loyalty, other
            $table->decimal('amount', 10, 2);
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('eligibility_notes')->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('is_active')->default(true);
            $table->boolean('stackable')->default(true); // can combine with other rebates
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('manufacturer_rebates'); }
};
