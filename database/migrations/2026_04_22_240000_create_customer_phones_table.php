<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-phone customers — each customer can have any number of phones,
 * each tagged with a label (Mobile / Home / Work / Other) and an
 * `is_sms_capable` flag (some lines are landline-only).
 *
 * Backfills the existing `customers.phone` and `customers.secondary_phone`
 * into the new table so nothing is lost. The legacy columns stay in place
 * so existing code that reads `$customer->phone` keeps working — they
 * track "the primary phone" via a model accessor we'll keep in sync.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('label', 30)->nullable();          // Mobile, Home, Work, Other…
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_sms_capable')->default(true); // landlines = false
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id']);
            $table->index('phone');
        });

        // Functional index on the last 10 digits for fast inbound auto-link.
        // Schema builder mangles raw expressions inside ->index(), so do it
        // via raw SQL.
        DB::statement("CREATE INDEX customer_phones_last10_idx ON customer_phones ((substring(regexp_replace(phone, '\\D', '', 'g') from '.{1,10}\$')))");

        // Backfill from customers.phone + customers.secondary_phone
        $rows = DB::table('customers')->whereNotNull('phone')->orWhereNotNull('secondary_phone')->get(['id', 'phone', 'secondary_phone']);
        foreach ($rows as $r) {
            $now = now();
            if (!empty($r->phone)) {
                DB::table('customer_phones')->insert([
                    'customer_id'    => $r->id,
                    'phone'          => $r->phone,
                    'label'          => 'Mobile',
                    'is_primary'     => true,
                    'is_sms_capable' => true,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
            if (!empty($r->secondary_phone) && $r->secondary_phone !== $r->phone) {
                DB::table('customer_phones')->insert([
                    'customer_id'    => $r->id,
                    'phone'          => $r->secondary_phone,
                    'label'          => 'Other',
                    'is_primary'     => false,
                    'is_sms_capable' => true,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }

    public function down(): void { Schema::dropIfExists('customer_phones'); }
};
