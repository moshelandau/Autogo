<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80);                 // dropdown label
            $table->text('body');                        // {first_name} {last_name} placeholders supported
            $table->string('category', 40)->nullable(); // for grouping in dropdown
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed a few useful starters
        \DB::table('sms_templates')->insert([
            ['label' => 'Vehicle ready for pickup',  'body' => "Hi {first_name} — your vehicle is ready for pickup at AutoGo. See you soon!", 'category' => 'rental',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Send DL photo please',      'body' => "Hi {first_name} — please text us a clear photo of the FRONT and BACK of your driver's license. Thanks!", 'category' => 'intake', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Insurance card request',    'body' => "Hi {first_name} — please text a photo of your auto insurance card so we can verify coverage.", 'category' => 'intake', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Bodyshop estimate ready',   'body' => "Hi {first_name} — your bodyshop estimate is ready. Reply YES and we'll text it over.", 'category' => 'bodyshop', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Towing dispatched',         'body' => "Driver dispatched. ETA ~20 min. We'll text when they arrive.", 'category' => 'towing', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Thanks - bodyshop will text','body'=> "Thanks ✅ — bodyshop will text you to schedule.", 'category' => 'general', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Thanks - dispatcher will call','body'=> "Thanks ✅ — dispatcher will call you in about 5 min.", 'category' => 'general', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Outstanding balance',       'body' => "Hi {first_name} — your account has an outstanding balance. Please call our office (845) 751-1133 to resolve. Thanks!", 'category' => 'general', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void { Schema::dropIfExists('sms_templates'); }
};
