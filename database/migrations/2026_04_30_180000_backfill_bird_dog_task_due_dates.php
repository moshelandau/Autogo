<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the 30-day default for Bird Dog payment tasks that already
 * exist on older deals. The new generator gives the right default for
 * fresh deals, but existing tasks were created with the old 3-day rule
 * and the user wants their due_date pushed out to ~1 month.
 *
 * Only touches incomplete tasks — completed ones are immutable history.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('deal_tasks')
            ->where('name', 'ilike', '%bird dog%')
            ->where('is_completed', false)
            ->update(['due_date' => now()->addDays(30)->toDateString()]);
    }

    public function down(): void
    {
        // No-op — the original 3-day defaults aren't recoverable; if you
        // need to revert, reset due_date manually for the affected rows.
    }
};
