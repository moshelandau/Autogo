<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What we previously called "Insurer" was actually the insurance
 * BROKER/agency the dealer works with — not the insurance carrier
 * itself (GEICO, Progressive, Nationwide, Kemper, etc.). Two
 * different things on a customer's policy:
 *   - Broker  — the agency that sold/services the policy
 *   - Carrier — the insurance company that underwrites it
 *
 * Rename insurers → insurance_brokers, deals.insurer_id → deals.broker_id,
 * and add a free-text `insurance_carrier` column on deals for the
 * actual carrier name.
 */
return new class extends Migration {
    public function up(): void
    {
        // Drop the FK constraint, rename column, recreate FK pointing at the renamed table
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('insurer_id');
        });

        Schema::rename('insurers', 'insurance_brokers');

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('broker_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained('insurance_brokers')
                ->nullOnDelete();
            $table->string('insurance_carrier')->nullable()->after('broker_id');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broker_id');
            $table->dropColumn('insurance_carrier');
        });

        Schema::rename('insurance_brokers', 'insurers');

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('insurer_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained()
                ->nullOnDelete();
        });
    }
};
