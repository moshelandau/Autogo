<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Reservation holds (security deposit auths) ──────────
        Schema::create('reservation_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('card_brand')->nullable();      // visa, mc, amex, etc
            $table->string('card_last4', 4)->nullable();
            $table->string('card_exp', 7)->nullable();     // MM/YY
            $table->string('card_token')->nullable();      // Sola token (no PAN stored)
            $table->string('sola_authorization_id')->nullable();
            $table->enum('status', ['authorized','captured','released','expired','failed'])->default('authorized');
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
        });

        // ── Extend rental_payments with tokenized card data ────
        Schema::table('rental_payments', function (Blueprint $table) {
            $table->string('card_brand')->nullable()->after('payment_method');
            $table->string('card_last4', 4)->nullable()->after('card_brand');
            $table->string('card_token')->nullable()->after('card_last4');
            $table->decimal('change_due', 10, 2)->nullable()->after('amount');
        });

        // ── Reservation: insurance source + lease agreement path ─
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('insurance_source', ['own_policy','credit_card','none'])->nullable()->after('notes');
            $table->string('insurance_policy_seen')->nullable()->after('insurance_source');
            $table->string('insurance_company_seen')->nullable()->after('insurance_policy_seen');
            $table->string('lease_agreement_path')->nullable()->after('insurance_company_seen');
            $table->string('hq_rentals_id')->nullable()->after('lease_agreement_path')->index();
        });

        // ── Customer: convenience flag column for dashboards ─
        // (Computed live from reservations.outstanding_balance + rental_payments,
        //  but cache it for fast dropdown rendering.)
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('cached_outstanding_balance', 10, 2)->default(0)->after('store_credit_balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_holds');
        Schema::table('rental_payments', function (Blueprint $t) {
            $t->dropColumn(['card_brand','card_last4','card_token','change_due']);
        });
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropColumn(['insurance_source','insurance_policy_seen','insurance_company_seen','lease_agreement_path','hq_rentals_id']);
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn('cached_outstanding_balance');
        });
    }
};
