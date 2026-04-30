<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tail of the consolidation in PR #15 — once preferences moved onto the
 * New Deal form (and onto the Workflow tab on Show), the "Capture
 * preferences" Lead-stage task became redundant. Same with
 * "Find vehicle match" + "Send quote" which the user asked to merge
 * into a single "Find vehicle match & send quote" task in Lead.
 *
 * Same rule as the previous cleanup: incomplete rows go, completed
 * rows stay (audit trail).
 */
return new class extends Migration {
    public function up(): void
    {
        $obsoleteNames = [
            'Capture preferences (style / budget / miles / passengers / color / brand)',
            'Find vehicle match',
            'Send quote', // was the lone Quote-stage send task; merged into Lead
        ];

        DB::table('deal_tasks')
            ->where('is_completed', false)
            ->whereIn('name', $obsoleteNames)
            ->delete();
    }

    public function down(): void
    {
        // Irreversible by design — DealController::show auto-sync re-creates
        // only the *current* STAGE_TASKS template, not these obsolete names.
    }
};
