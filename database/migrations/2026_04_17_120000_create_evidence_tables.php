<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence / dispute-protection infrastructure.
 *
 * Design rules:
 *  - No UPDATE paths for audit_logs and agreement_revisions (rows are append-only)
 *  - Each agreement_revision carries a SHA256 hash plus the hash of the previous
 *    revision for that reservation → tamper-evident chain
 *  - signatures table captures IP + user agent + timestamp so we can prove who signed
 *  - communication_logs records every email/SMS/call tied to a customer/reservation
 */
return new class extends Migration {
    public function up(): void
    {
        // Immutable PDF revision chain — every state change produces a snapshot
        Schema::create('agreement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['rental_agreement', 'return_receipt'])->index();
            $table->enum('action', [
                'reservation_created', 'dates_changed', 'vehicle_assigned',
                'signed', 'pickup', 'rented', 'return', 'completed',
                'payment_created', 'hold_authorized', 'hold_released', 'hold_captured',
                'manual_change', 'other',
            ]);
            $table->string('pdf_path');
            $table->string('sha256', 64)->index();          // hash of this PDF
            $table->string('prev_sha256', 64)->nullable();  // hash of previous revision in chain
            $table->json('snapshot');                        // structured data at moment of snapshot
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // NO updated_at, NO soft deletes — rows are append-only
            $table->index(['reservation_id', 'document_type', 'created_at'], 'agr_rev_idx');
        });

        // Signatures with full provenance (dispute evidence)
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->morphs('signable');                       // Reservation, Deal, etc.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('signer_name')->nullable();
            $table->text('signature_data_url');               // base64 PNG of the drawn signature
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_info')->nullable();        // mobile / desktop / tablet
            $table->decimal('geo_lat', 10, 7)->nullable();
            $table->decimal('geo_lng', 10, 7)->nullable();
            $table->string('sha256', 64);                     // hash of signature_data_url+ip+ua+ts
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamps();
        });

        // Global, immutable audit log — every state-changing request
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();           // snapshotted in case user renamed/deleted later
            $table->string('method', 10)->nullable();
            $table->string('path')->index();
            $table->nullableMorphs('subject');                 // e.g. Reservation #123
            $table->string('action')->nullable();              // e.g. "payment_created", "pickup", "status_changed"
            $table->json('changes')->nullable();               // before/after for updates
            $table->json('params')->nullable();                // request payload (scrubbed)
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('source')->default('web');          // web | mobile_app | api | internal_job
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at, no soft deletes
            $table->index(['created_at', 'path'], 'audit_created_path');
        });

        // Emails, SMS, calls — communication with the customer tied to reservation/deal/claim
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('subject');                 // Reservation, Deal, Claim
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['email', 'sms', 'call', 'letter', 'portal_message']);
            $table->enum('direction', ['outbound', 'inbound']);
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();           // [{path, name, sha256}]
            $table->string('external_ref')->nullable();        // msg id from Twilio/Telebroad/Postmark
            $table->enum('status', ['queued','sent','delivered','failed','read','received'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('agreement_revisions');
    }
};
