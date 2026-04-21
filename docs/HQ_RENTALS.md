# HQ Rentals

> **Status:** ❌ **API not verified.** HQ Rentals subdomain known; API key not yet provided; our `hq:extract-leases` command tries 3 plausible endpoint paths that I **have not confirmed actually exist**.

## ✅ Verified

- Subdomain: **`highrental.us5.hqrentals.app`** (verified — user logged in during this session)
- HQ is the source-of-truth for historical rental data that has already been imported into AutoGo (5,814 reservations, 2,441 customers)
- Each reservation has an `hq_rentals_id` column on our `reservations` table (nullable; populated for imports that came from HQ)

## ❌ NOT verified

### API endpoints
Our code tries these paths in order and keeps the first PDF response:
- `/api/integration/v1/reservations/{hqId}/contract.pdf`
- `/api/integration/v1/reservations/{hqId}/agreement`
- `/api/integration/v1/reservations/{hqId}/documents/contract`

**None of these have been confirmed against HQ Rentals' real API.** They were guesses based on common REST conventions.

### Auth
We pass `Bearer {api_key}` with header `Accept: application/pdf`. **HQ's real auth method is unknown** — could be different (API key header, basic auth, OAuth, etc.).

### API key
No key has been provided. `HQ_RENTALS_API_KEY` / the Settings field are empty.

## Before using `php artisan hq:extract-leases`

1. Ask HQ Rentals support for:
   - API availability / docs URL
   - Auth method + how to provision a key
   - The actual URL path for pulling a rental agreement PDF by reservation id
2. Update `app/Console/Commands/ExtractHqLeaseAgreements.php` with the verified path
3. Run `--reservation=<id>` on a single known reservation in staging and inspect the response
4. If it works, run `--limit=10`, verify PDFs open properly, then `--full`

## Why this matters

If we can pull the HQ Rentals agreement PDF for each historical reservation:
- Attach as `lease_agreement_path` (we already have the column)
- Customer-history timeline will show the original lease PDF
- Full evidence for any pre-AutoGo dispute

---
Files:
- `app/Console/Commands/ExtractHqLeaseAgreements.php` (untested stub)
- `config/services.php` → `hq_rentals` section
