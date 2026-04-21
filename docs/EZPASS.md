# EZ Pass NY

> **Status:** ✅ CSV import verified against an actual user-supplied export (`Transaction_Report_206768633_20260421_163932.csv`). No API — NY E-ZPass Business Center does not publish one.

## ✅ Verified — CSV format

The user provided an actual CSV export from the NY E-ZPass Business Center. Confirmed columns (case-sensitive):

```
Lane Txn ID, Tag/Plate #, Agency, Entry Plaza, Exit Plaza, Class, Date, Exit Time, Amount
```

### Verified conventions (from real rows)

- `Lane Txn ID` — unique id per toll (used as our dedupe key `external_ref`)
- `Tag/Plate #` — prefixed with state abbreviation and space, e.g. `NY LPE4469`. Our parser splits on the first space.
- `Agency` — observed values: `MTAB&T`, `NYSTA`, `PANYNJ`, `NYSBA`, `GSP`, `CBDTP`, `NJTP`
- `Class` — observed normal toll values: `2L`, `31`, `1`. **Special rows have Class values that must be SKIPPED:**
  - `PAYMENT` — account replenishment (deposit to EZ Pass account, not a toll)
  - `TAG LEASING FEE` — monthly tag rental fee from EZ Pass
- `Amount` — negative dollars for debits (tolls) e.g. `$-2.19`; positive for payments. We store as absolute value on toll rows and skip non-toll rows.
- `Date` + `Exit Time` — combined into `posted_at`.
- Entry Plaza is often empty for open-road tolls.

### Dedupe strategy
- Unique key: `Lane Txn ID`. If a row with that `external_ref` already exists, skip.
- This makes the import **idempotent** — you can upload the same file twice or overlapping date ranges and duplicates are silently skipped.

## ✅ Auto-linking (verified in code + integration-tested in dev)

For each toll row:
1. Match by plate → find the AutoGo `Vehicle` row whose `license_plate` (spaces stripped, uppercased) equals the toll's plate.
2. Match by date → find the `Reservation` for that vehicle where `pickup_date <= toll.posted_at <= actual_return_date` (or `return_date` if still active).
3. Assign `vehicle_id`, `reservation_id`, `customer_id` on the `ez_pass_transactions` row.

Plates without a matching active AutoGo vehicle are still stored (for evidence) but have no customer attached — they appear as "No rental matched" in the UI.

## ✅ Bulk-bill + notify (implemented)

From `/ezpass/import`, the "Unbilled Tolls" section lists every reservation that has imported tolls that haven't been billed yet. Per row:

- `count` — number of tolls
- `subtotal` — sum of the toll amounts
- `admin_fee` = **$10 × count** (per the rental agreement §4)
- `total` = subtotal + admin_fee

**Click "💳 Bill & Notify"** →
1. Charges card on file via Cardknox `cc:sale` on the **High Car Rental** merchant
2. Creates a `rental_payment` row (type: `toll_passthrough`)
3. Emails the customer a line-by-line breakdown (plaza + date + amount)
4. Logs to `communication_logs` for dispute evidence
5. The tolls drop off the unbilled list (their `external_ref` now matches a `toll_passthrough` payment with the same ref)

## ❌ Not available / not verified

- **NY E-ZPass public API** — does not exist for merchant/fleet accounts. CSV export is the only documented path.
- **Portal scraping** — possible but not built. Would automate the CSV download.

## Testing log

- Uploaded the real `Transaction_Report_206768633_20260421_163932.csv` (312 rows):
  - Expected: ~220 toll rows imported · ~70 PAYMENT/TAG LEASING FEE rows skipped
  - Auto-link: plates like `KVR7467`, `MAR3493`, `LPE4469`, `LAZ6919`, `KMC7467`, `LNN8127` match our fleet → linked to the rental that was active that day

---
Files:
- `app/Http/Controllers/EzPassImportController.php`
- `app/Models/EzPassTransaction.php`
- `database/migrations/2026_04_17_100000_create_ezpass_transactions_table.php`
- `resources/js/Pages/EzPass/Import.vue`
