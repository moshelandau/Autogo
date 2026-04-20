<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->boolean('can_receive_sms')->default(true);
            $table->string('address')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->default('US');
            $table->string('drivers_license_number')->nullable();
            $table->date('dl_expiration')->nullable();
            $table->string('dl_state')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('insurance_company')->nullable();
            $table->string('insurance_policy')->nullable();
            $table->integer('credit_score')->nullable();
            $table->decimal('store_credit_balance', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('hq_rentals_id')->nullable()->comment('HQ Rentals customer ID for sync');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('customers'); }
};
