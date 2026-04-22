<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar query was scanning the whole table every render. The hot WHERE
 * is `pickup_date <= :end AND return_date >= :start`. A composite index
 * on those two columns + a status index for the IN filter speeds it up
 * dramatically (from ~seconds to <100ms on prod's reservation count).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['pickup_date', 'return_date'], 'reservations_calendar_idx');
            $table->index('status',                       'reservations_status_idx');
            $table->index('vehicle_id',                   'reservations_vehicle_idx');
            $table->index('vehicle_class',                'reservations_vclass_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_calendar_idx');
            $table->dropIndex('reservations_status_idx');
            $table->dropIndex('reservations_vehicle_idx');
            $table->dropIndex('reservations_vclass_idx');
        });
    }
};
