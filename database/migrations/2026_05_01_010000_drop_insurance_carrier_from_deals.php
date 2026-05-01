<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts the `insurance_carrier` column added in
 * 2026_04_30_080000_rename_insurers_to_brokers.
 *
 * Per user feedback: the carrier name is already on the insurance
 * doc that gets uploaded — duplicating it on the deal record adds no
 * value and just creates two places to drift.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'insurance_carrier')) {
                $table->dropColumn('insurance_carrier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('insurance_carrier')->nullable()->after('broker_id');
        });
    }
};
