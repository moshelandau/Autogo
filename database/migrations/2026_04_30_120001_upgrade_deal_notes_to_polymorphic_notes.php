<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades the simple deal_notes table into a polymorphic notes table that
 * can attach to Deals, Customers (and any other future model via
 * notable_type/notable_id) and carries the new mention/assignment/todo
 * machinery: subject, reminder_date, is_resolved, assigned_to.
 *
 * Existing rows are preserved — each carries forward as a Deal-attached
 * note (notable_type=App\Models\Deal, notable_id=deal_id).
 */
return new class extends Migration {
    public function up(): void
    {
        // Step 1 — add the new columns alongside deal_id so we can backfill.
        Schema::table('deal_notes', function (Blueprint $table) {
            $table->string('notable_type')->nullable()->after('id');
            $table->unsignedBigInteger('notable_id')->nullable()->after('notable_type');
            $table->string('subject', 120)->nullable()->after('body');
            $table->date('reminder_date')->nullable()->after('subject');
            $table->boolean('is_resolved')->default(false)->after('reminder_date');
            $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        // Step 2 — backfill polymorphic columns from deal_id.
        DB::table('deal_notes')->whereNotNull('deal_id')->update([
            'notable_type' => 'App\\Models\\Deal',
            'notable_id'   => DB::raw('deal_id'),
        ]);

        // Step 3 — drop deal_id (FK first, then column), rename table.
        Schema::table('deal_notes', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
            $table->dropColumn('deal_id');
        });

        Schema::rename('deal_notes', 'notes');

        // Step 4 — make the new poly columns required + indexed now that
        // every row has values.
        Schema::table('notes', function (Blueprint $table) {
            $table->string('notable_type')->nullable(false)->change();
            $table->unsignedBigInteger('notable_id')->nullable(false)->change();
            $table->index(['notable_type', 'notable_id']);
            $table->index('reminder_date');
            $table->index('is_resolved');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['notable_type', 'notable_id']);
            $table->dropIndex(['reminder_date']);
            $table->dropIndex(['is_resolved']);
            $table->dropForeign(['assigned_to']);
            $table->unsignedBigInteger('deal_id')->nullable();
        });
        DB::table('notes')
            ->where('notable_type', 'App\\Models\\Deal')
            ->update(['deal_id' => DB::raw('notable_id')]);
        Schema::rename('notes', 'deal_notes');
        Schema::table('deal_notes', function (Blueprint $table) {
            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
            $table->dropColumn(['notable_type', 'notable_id', 'subject', 'reminder_date', 'is_resolved', 'assigned_to']);
        });
    }
};
