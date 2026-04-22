<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lease_application_sessions', function (Blueprint $table) {
            // Per-field approval flags so staff must verify the customer's self-
            // reported numbers before the application can be emailed to a dealer.
            $table->jsonb('approvals')->nullable()->after('collected'); // {field_name: {by:user_id, at:iso8601}}
        });
    }

    public function down(): void
    {
        Schema::table('lease_application_sessions', function (Blueprint $table) {
            $table->dropColumn('approvals');
        });
    }
};
