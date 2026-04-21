# Evidence / Dispute Defense System

> **Status:** ✅ **Built & tested in dev.** Schema is in place; observers fire on key events; immutable rules enforced at the model level.

## Purpose

Anything that might prevent a loss in a customer dispute (Beis Din or otherwise) is tracked with timestamped, append-only, hash-chained evidence.

## Tables (all append-only — UPDATE/DELETE blocked at the model level)

### `agreement_revisions`
Every time a Reservation changes meaningfully, a new PDF snapshot is generated and saved with:
- `pdf_path` — the rendered PDF on disk
- `sha256` — hash of the PDF binary
- `prev_sha256` — hash of the previous revision in the chain (per reservation × document_type)
- `snapshot` — structured JSON of reservation + customer + vehicle + active hold at that exact moment
- `action` — what triggered the snapshot: `reservation_created`, `vehicle_assigned`, `signed`, `pickup`, `rented`, `return`, `completed`, `manual_change`, `payment_created`, `hold_authorized/released/captured`
- `document_type` — `rental_agreement` or `return_receipt`
- `created_by` — user id (null for system jobs)
- `ip_address`, `user_agent` — captured from the request that triggered the change
- `created_at` — server time (no `updated_at`)

**Tamper-evidence:** because each row carries the SHA256 of the previous row's PDF, altering any historical PDF breaks the chain. To change one PDF and have it look legitimate, an attacker would need to regenerate every subsequent revision *and* edit `prev_sha256` on every row — and this table doesn't allow updates.

### `signatures`
Every captured customer signature (rental agreement, damage waiver, etc.) saves:
- Signature image (base64 PNG of the drawn signature)
- `customer_id` and polymorphic `signable` (Reservation, Deal, etc.)
- `ip_address`, `user_agent`, `device_info`, `geo_lat`, `geo_lng`
- `sha256` — hash of `signature_data_url + ip + ua + ts` (binds the signature to its provenance)
- `signed_at` (server time)

`DELETE` is blocked at the model level. The signature also surfaces on the rental-agreement PDF footer with timestamp, IP, and the first 16 chars of its SHA256.

### `audit_logs`
Every state-changing HTTP request is logged via `App\Http\Middleware\AuditLogMiddleware`:
- `user_id` + snapshotted `user_name` (so renaming/deleting users doesn't lose history)
- `method`, `path`, response `status_code`, `duration_ms`
- `params` — request body, with sensitive keys auto-redacted: `password`, `password_confirmation`, `_token`, `cvc`, `cvv`, `card_number`, `secret_key`, `api_key`, `client_secret`, `signature_data_url`, `auth_token`. Long strings >2000 chars truncated.
- `ip_address`, `user_agent`
- `source` — `web` / `mobile_app` / `api` / `internal_job` (from `X-Client-Source` header)
- Logged for: every POST/PUT/PATCH/DELETE, plus auth events (`/login`, `/logout`, `/two-factor-challenge`)

UI: `/audit-logs` (paginated, searchable by path/user/action, filterable by method/source). Click any row to expand and see params + UA + change diffs.

### `communication_logs`
Every email/SMS/call/letter to a customer:
- Polymorphic `subject` (Reservation/Deal/Claim)
- `customer_id`, `user_id`
- `channel` (email/sms/call/letter/portal_message), `direction` (outbound/inbound)
- `from`, `to`, `subject`, `body`
- `attachments` — JSON array `[{path, name, sha256}]`
- `external_ref` — provider-side message id (Twilio, Postmark, Telebroad, etc.)
- `status` — queued / sent / delivered / failed / read / received
- `sent_at`

This is the place you go when a customer says "I never received notification about that charge."

## Auto-snapshot triggers

`App\Observers\ReservationObserver` fires `AgreementRevisionService::snapshot()` on:
- `created` → `reservation_created` (rental_agreement type)
- `updated` with `actual_return_date` change → `return` (return_receipt type)
- `updated` with `actual_pickup_date` change → `pickup` (rental_agreement type)
- `updated` with `status` change → `rented` / `completed` / `manual_change`
- `updated` with `vehicle_id` change → `vehicle_assigned`

`App\Observers\PaymentBalanceObserver` triggers reservation balance + customer cached_outstanding_balance recalc on RentalPayment save/delete (no PDF, but is part of the evidence chain because it's audit-logged).

## Revision API (verified)

| Route | What it does |
|---|---|
| `GET /rental/reservations/{id}/revisions` | JSON list of all snapshots for that reservation, with download/email URLs and the user who triggered each one |
| `GET /revisions/{id}/download` | Downloads the specific snapshot PDF |
| `POST /revisions/{id}/email` | Emails the snapshot PDF to the customer (or `to` override) and writes a row to `communication_logs` |

## Files

- `database/migrations/2026_04_17_120000_create_evidence_tables.php`
- `app/Models/AgreementRevision.php`
- `app/Models/Signature.php`
- `app/Models/AuditLog.php`
- `app/Models/CommunicationLog.php`
- `app/Observers/ReservationObserver.php`
- `app/Observers/PaymentBalanceObserver.php`
- `app/Services/AgreementRevisionService.php`
- `app/Http/Middleware/AuditLogMiddleware.php`
- `app/Http/Controllers/AgreementRevisionController.php`
- `app/Http/Controllers/AuditLogController.php`
- `resources/js/Pages/AuditLogs/Index.vue`
