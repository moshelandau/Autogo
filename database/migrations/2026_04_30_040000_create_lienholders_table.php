<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lienholders — banks/finance companies that hold title until paid off.
 * Mirrors xDeskPro's Deal Information → Lienholder typeahead.
 *
 * Distinct from `lenders`: a lender originates the loan; the lienholder
 * is the title holder for DMV registration. Often the same company,
 * sometimes not (sold-loan scenarios).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('lienholders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip')->nullable();
            $table->string('elt_number')->nullable(); // Electronic Lien & Title number
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('lienholder_id')->nullable()->after('insurer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lienholder_id');
        });
        Schema::dropIfExists('lienholders');
    }
};
