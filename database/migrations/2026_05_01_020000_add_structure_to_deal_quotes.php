<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `structure` JSONB on deal_quotes — holds the Quote Wizard step-1
 * inputs that don't have first-class columns:
 *   vehicle: { vin, type, year, make, model, trim, odometer, options }
 *   trade:   { allowance, acv, payoff, owned_or_leased }
 *   customer:{ zip, state, county }
 *   dealer:  { zip }
 *   drive_off:{ type: 'total_drive_off'|'lease_cap_reduction'|'sign_and_drive', amount }
 *   lender_loyalty: bool
 *   acquisition_fee_type: 'upfront'|'capped' (also has a column already, kept here for wizard echo)
 *   applied_rebate_ids: ['mc_offer_id', ...]
 *
 * Stashing in JSONB instead of bloating the table — these are wizard
 * draft inputs; the worksheet (PR D) will compute the canonical values.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_quotes', function (Blueprint $table) {
            $table->jsonb('structure')->nullable()->after('notes');
            $table->boolean('is_draft')->default(false)->after('is_selected');
        });
    }

    public function down(): void
    {
        Schema::table('deal_quotes', function (Blueprint $table) {
            $table->dropColumn(['structure', 'is_draft']);
        });
    }
};
