<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for multi-assignee notes. email_sent flag is the de-dup gate for
 * reminder-due emails — flipped true once the scheduled command notifies a
 * user, reset to false if the reminder date is changed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('note_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('email_sent')->default(false);
            $table->timestamps();
            $table->unique(['note_id', 'user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('note_user'); }
};
