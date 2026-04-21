# TowBook

> **Status:** 🟡 partial — the HTML Motor Club Report scrape is verified (485 historical jobs imported). The REST API code is a stub that has **never been executed against a live TowBook key**.

## ✅ Verified

### Account on file
From the TowBook Settings → Overview screen the user opened:
- Account name: **Auto Go**
- Account ID: **TB2254354** (from referral link `towbook.com/signup/TB2254354`)
- Internal company ID: **AG312995** (from forward-email address `AG312995@towbook.com`)
- Forwarded-to email: `george@autogoco.com`
- User: Moshe (Manager)
- Login: user `8455008085` / password shared out of band

### HTML scrape — verified working
- Navigate to Reports → Dispatching Reports → **Service/Motor Club Report** (URL path observed: `Reports/Dispatching/Viewer?report=motorclub`)
- Date range + Driver + Truck filters
- "Export to CSV/Excel" link or scrape the rendered `<table>` directly
- **Verified columns (from real data):** `Call#`, `Service Provider`, `Driver`, `Truck`, `Date`, `Type`, `Purchase Order #`, `Membership #`, `Motor Club Dispatch #`, `Money Received`, `PO Amount Total`
- Verified ~485 historical jobs in the account

### What was actually imported (verified counts in production DB)
- **485 tow jobs** (job numbers `TB-00001`–`TB-00488`)
- **Providers:** Agero (Swoop) 271 · Allstate 191 · Cash 22 · Urgent.ly 1
- **Drivers:** yissucher davidowitz 144 · Mark 145 · Nando 107 · Darwin 10
- **Total billed:** $99,735.84

## ❌ NOT verified / NOT tested

### REST API
- We wrote `App\Services\TowBookService` and `sync:towbook` command **on the assumption** that TowBook exposes a REST API at `https://api.towbook.com/v1/...` with OAuth2 `client_credentials` auth.
- **This is an assumption.** We do not have a client_id/client_secret for this account, have not successfully hit that endpoint, and the URL / auth method / resource paths may be wrong.
- Before trusting `sync:towbook`, we must:
  1. Contact `support@towbook.com` to confirm API is available on this plan
  2. Get real client_id / client_secret
  3. Point our service at the URL they specify
  4. Run `php artisan sync:towbook --full` in staging and log every response
  5. Correct the field mapping in `SyncTowBook.php` based on actual JSON shape
- The hourly scheduled run (`0 * * * *`) is disabled-by-default behavior: without credentials the service's `isConfigured()` returns false and the job is a no-op.

### The 485 existing jobs are SUMMARY-only
The HTML report only exposed 11 columns. **We do NOT have** per-job:
- Pickup address / dropoff address (the job detail pages have these, but we didn't scrape them)
- Customer name / phone
- Vehicle year/make/model/VIN

To enrich, someone would need to click into each of the 485 detail pages (or use the API if we ever get it) and capture those fields.

## Integration options (honest rundown)

| Path | Works? | Effort |
|---|---|---|
| Official REST API | Unknown — not tested. Ask TowBook support whether it's available on this plan and at what price | Blocked on vendor |
| HTML scrape of dispatching board | ✅ proven | Scheduled Puppeteer/Playwright job using the saved session — not yet built |
| Email parsing of motor-club dispatch emails | ✅ inbox already forwards to george@autogoco.com | Not built. If we set up inbound email, Agero/Allstate/Honk emails can be parsed directly, bypassing TowBook for initial job creation |

---
Files:
- `app/Services/TowBookService.php` (API stub — untested)
- `app/Console/Commands/SyncTowBook.php` (API stub — untested)
- `app/Console/Commands/ImportTowBookJson.php` (verified — ingested the manually-scraped JSON)
