<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deal_action_items — ad-hoc todos staff create on a deal,
 * separate from the canonical STAGE_TASKS workflow tasks.
 * Mirrors the "Action Items" section under Current Tasks on
 * xDeskPro's deal page.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['deal_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_action_items');
    }
};
