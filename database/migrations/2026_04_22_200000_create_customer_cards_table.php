<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tokenized cards on file (NEVER stores the PAN — only the Cardknox xToken
     * + display-safe last4/brand/exp). The full PAN flows through the server
     * briefly during cc:save and is immediately discarded; iFields integration
     * (PCI-safe iframe entry) remains the future-proof path per docs/CARDKNOX.md.
     */
    public function up(): void
    {
        Schema::create('customer_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('account', 32);                 // 'autogo' | 'high_rental' (which Sola merchant tokenized it)
            $table->string('x_token');                     // Cardknox xToken — opaque, no PAN
            $table->string('brand', 20)->nullable();       // visa | mc | amex | discover | etc
            $table->string('last4', 4)->nullable();
            $table->string('exp', 5)->nullable();          // MM/YY
            $table->string('cardholder')->nullable();
            $table->string('label')->nullable();           // user-friendly tag e.g. "Personal", "Company Amex"
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cards');
    }
};
