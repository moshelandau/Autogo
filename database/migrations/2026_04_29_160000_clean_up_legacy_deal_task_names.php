<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup of incomplete deal_tasks rows with names that have been
 * superseded by a renamed or merged task in Deal::STAGE_TASKS.
 *
 * Completed tasks (is_completed = true) are LEFT ALONE — they're audit
 * trail of work actually done, even if the template wording has since
 * changed. Only incomplete duplicates/leftovers get deleted.
 *
 * After this migration runs, the DealController::show auto-sync will
 * lazily generate the current STAGE_TASKS template tasks for every
 * stage on next view, so nothing is lost from the visible task list.
 */
return new class extends Migration {
    public function up(): void
    {
        $obsoleteNames = [
            // Legacy (pre-PR #9) names
            'Desk Deal',
            'Send Quote',
            'Follow Up For Acceptance',
            'Send Application',
            'Receive Application',
            "Receive Driver's License",
            'Submit Application',
            'Get Approval',
            'Collect Insurance',
            'Transfer Registration',
            'Loyalty/Conquest',
            'Rebate Documentation',
            'Schedule Delivery',
            'Collect COD',
            'Collect Bird Dog',
            'Collect Lease Agreement',
            // Names from PR #9 that were merged in this PR
            'Receive full application',
            "Receive driver's license — front",
            "Receive driver's license — back",
            'Optional: Collect conquest documentation',
            'Optional: Collect rebate documentation',
            'Schedule delivery',
            'Confirm car is ready for pickup',
            'Send pickup details to customer',
        ];

        DB::table('deal_tasks')
            ->where('is_completed', false)
            ->whereIn('name', $obsoleteNames)
            ->delete();
    }

    public function down(): void
    {
        // Irreversible — once deleted, the DealController::show auto-sync
        // will only re-create the *current* STAGE_TASKS template, not the
        // obsolete names. That's the intended behavior.
    }
};
