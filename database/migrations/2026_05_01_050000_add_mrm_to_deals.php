<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MRM = Maximum Retail Markup. Honda/Acura publish this as the
 * dealer-protected price ceiling — most other OEMs use invoice +
 * holdback as the equivalent. Sits alongside MSRP and Invoice on
 * the deal record so the Worksheet can show all three together
 * (xDeskPro Vehicle Info parity).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'mrm')) {
                $table->decimal('mrm', 12, 2)->nullable()->after('msrp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'mrm')) {
                $table->dropColumn('mrm');
            }
        });
    }
};
