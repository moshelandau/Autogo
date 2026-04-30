<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deal_shares — pivot for which staff users can view a given deal
 * (xDeskPro deal page → Sharing tab → Internal Sharing).
 *
 * Owner (deal.salesperson_id) is implicitly always included; nothing
 * here is required to render the deal for them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['deal_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_shares');
    }
};
