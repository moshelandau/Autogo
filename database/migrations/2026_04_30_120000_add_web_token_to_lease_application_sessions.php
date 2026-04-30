<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * web_token lets a customer fill out their lease application via a
 * public web page (https://app.autogoco.com/apply/{token}) instead of
 * (or in addition to) the SMS bot. The token is a random 40-char
 * string scoped to a single LeaseApplicationSession — typing on a
 * phone keyboard is brutal compared to a real form with a date picker
 * and file upload, so we offer both paths.
 *
 * The token alone authorizes access to that one session — there is no
 * separate login. We rely on the unguessability of the random string
 * and (later) an expires_at on the session for security.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('lease_application_sessions', function (Blueprint $table) {
            $table->string('web_token', 64)->nullable()->unique();
            // OTP gate for the public form. The token in the URL identifies
            // the session; the OTP proves the holder of the URL is also the
            // person on the linked phone (could re-receive the SMS).
            $table->string('web_otp_hash', 80)->nullable();      // bcrypt of 6-digit code
            $table->timestamp('web_otp_expires_at')->nullable();
            $table->timestamp('web_verified_at')->nullable();    // current browser session is verified (auto-set on first visit, then expires)
            $table->timestamp('web_first_visited_at')->nullable(); // tracks whether the link has ever been opened — first visit is trusted, returns require OTP
        });

        // Backfill tokens for any active sessions so existing customers can
        // pick up a form link mid-flow.
        DB::table('lease_application_sessions')
            ->whereNull('completed_at')
            ->whereNull('aborted_at')
            ->whereNull('web_token')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('lease_application_sessions')
                    ->where('id', $row->id)
                    ->update(['web_token' => Str::random(40)]);
            });
    }

    public function down(): void
    {
        Schema::table('lease_application_sessions', function (Blueprint $table) {
            $table->dropColumn(['web_token', 'web_otp_hash', 'web_otp_expires_at', 'web_verified_at', 'web_first_visited_at']);
        });
    }
};
