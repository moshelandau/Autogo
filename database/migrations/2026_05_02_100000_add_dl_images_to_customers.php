<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver's-license image storage on customers. The mobile worker app captures
 * front + back photos at pickup; we store paths so the agreement & any future
 * dispute has the evidence on file. The `dl_ocr` JSON stash keeps whatever
 * the Anthropic vision pass extracted (name, dob, expiration, address...) so
 * we can reconcile against typed-in fields without re-OCR'ing.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('dl_front_image_path')->nullable()->after('date_of_birth');
            $table->string('dl_back_image_path')->nullable()->after('dl_front_image_path');
            $table->jsonb('dl_ocr')->nullable()->after('dl_back_image_path');
            $table->timestampTz('dl_verified_at')->nullable()->after('dl_ocr');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['dl_front_image_path', 'dl_back_image_path', 'dl_ocr', 'dl_verified_at']);
        });
    }
};
