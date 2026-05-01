<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dealer markdowns — custom offers AutoGo gets from individual dealer
 * reps that aren't in any scraped OEM feed. "Hudson Honda gives me
 * an extra $500 on CR-Vs through May", "Garden State Mazda has $1k
 * dealer cash this month", etc.
 *
 * Lives alongside MarketCheck OEM incentives — the Wizard's Available
 * Rebates picker merges both into one list.
 *
 *   - dealer_id     → links to the dealers (PR #81) row when known
 *   - dealer_name   → free-text fallback when no dealer record yet
 *   - amount        → cashback in dollars (positive = customer benefit)
 *   - title         → short description ("Spring Bonus", "Loyalty Adder")
 *   - make/model    → optional vehicle scope (null = any vehicle)
 *   - year_from/to  → optional model-year range
 *   - valid_*       → date window
 *   - notes         → context (rep name, call date, conditions)
 *   - is_active     → soft toggle
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('dealer_markdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dealer_name')->nullable(); // free-text if no dealer record
            $table->decimal('amount', 10, 2);
            $table->string('title');
            $table->string('make', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->integer('year_from')->nullable();
            $table->integer('year_to')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_through')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'make']);
            $table->index('valid_through');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_markdowns');
    }
};
