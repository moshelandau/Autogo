<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual sort order for deals within a kanban stage.
 *
 * NULL = "new, not yet manually sorted" — these float to the top via the
 * COALESCE in LeasingService::getDealsByStage. Once a user drags a card,
 * its position (and the positions of cards around it) get persisted.
 *
 * Backfills existing rows with sort_order = updated_at-DESC rank within
 * each stage so the kanban renders in the same order it does today.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->integer('sort_order')->nullable();
            $table->index(['stage', 'sort_order']);
        });

        $stages = DB::table('deals')->distinct()->pluck('stage');
        foreach ($stages as $stage) {
            $ids = DB::table('deals')
                ->where('stage', $stage)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id');
            foreach ($ids as $i => $id) {
                DB::table('deals')->where('id', $id)->update(['sort_order' => $i]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex(['stage', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
