<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('daily_rate', 12, 2)->default(0)->change();
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('tax_amount', 12, 2)->default(0)->change();
            $table->decimal('discount_amount', 12, 2)->default(0)->change();
            $table->decimal('addons_total', 12, 2)->default(0)->change();
            $table->decimal('total_price', 12, 2)->default(0)->change();
            $table->decimal('total_paid', 12, 2)->default(0)->change();
            $table->decimal('total_refunded', 12, 2)->default(0)->change();
            $table->decimal('security_deposit', 12, 2)->default(0)->change();
            $table->decimal('outstanding_balance', 12, 2)->default(0)->change();
        });
    }

    public function down(): void {}
};
