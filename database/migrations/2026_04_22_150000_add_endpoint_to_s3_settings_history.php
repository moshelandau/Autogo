<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('s3_settings_history', function (Blueprint $table) {
            $table->string('endpoint')->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('s3_settings_history', function (Blueprint $table) {
            $table->dropColumn('endpoint');
        });
    }
};
