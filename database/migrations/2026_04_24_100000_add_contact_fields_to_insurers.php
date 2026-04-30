<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original insurers table from 2026_04_17 used a single `contact_name`.
 * The legacy xDeskPro UI tracks first/last name separately for the broker
 * contact, plus an address. Add those + an index on the company name.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('address')->nullable()->after('claims_email');
            $table->index('name');
        });

        // Backfill: split existing contact_name into first/last
        \DB::table('insurers')->whereNotNull('contact_name')->orderBy('id')->each(function ($row) {
            $parts = preg_split('/\s+/', trim((string) $row->contact_name), 2);
            \DB::table('insurers')->where('id', $row->id)->update([
                'first_name' => $parts[0] ?? null,
                'last_name'  => $parts[1] ?? null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('insurers', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropColumn(['first_name', 'last_name', 'address']);
        });
    }
};
