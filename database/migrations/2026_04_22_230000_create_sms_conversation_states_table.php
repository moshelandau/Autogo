<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-conversation state (resolved / not) keyed by the normalized last-10
 * digits of the OTHER party's phone number. Kept separate from
 * communication_logs (which is per-message) because "resolved" is a
 * property of the thread, not individual messages.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->string('phone_last10', 10)->unique();       // normalized
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolve_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('sms_conversation_states'); }
};
