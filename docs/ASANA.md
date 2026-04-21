# Asana (claims backfill)

> **Status:** ✅ **verified & complete.** The Asana backfill was run successfully against the real claims workspace.

## ✅ Verified in production

### Source
- Asana workspace containing the legacy "Claims" project
- Per-claim data lived in the **task notes** (free text) — no structured custom fields on these tasks

### What was extracted (verified from the final run output)
- **393 insurance-entry rows** created across **334 claims**
- **Top insurers** (pattern-matched and canonicalized): Progressive 97 · Geico 48 · Nationwide 37 · State Farm 28 · Allstate 21 · Travelers 18 · National General 15 · Erie 12 · Safeco 9 · NYCM 6 · Liberty Mutual 5 · Others (Kingstone, Affirmative, Hereford, Great West, Mesa Underwriters, Maya, Utica, Guard, etc.)
- **"Unknown" insurer count**: 15 (claims where we captured a claim number but the insurer line didn't match our whitelist and the fallback heuristic couldn't infer it)
- **Adjuster data** found on only 1 claim (#25 — Moshe Fisher / Vincent Pettica examiner @ DFS NY). **This is a real limit of the source data** — the Asana notes rarely contained structured adjuster info. Rich adjuster data lives in CCC ONE (see `CCC_ONE.md` — not yet written because CCC ONE has no public API).

### Parser approach (verified in the code)
Line-by-line regex walk of each task's notes:
1. **Claim-number line:** `/^claim\s*(?:number|num|nr|no|#)?\s*[:#]?\s*([A-Z0-9][\w\-\s]{3,})\s*$/i` — requires at least one digit in the match; excludes bare "Number" / "Num" words.
2. **Insurer whitelist:** Progressive, Geico, Allstate, etc. — expanded over 2 iterations to handle common misspellings (`Formers` → `Farmers`, `StateFarm` → `State Farm`).
3. **Fallback:** line immediately after a Claim-Number line, if ≥3 letters and not a phone/email/VIN/plate.
4. **Adjuster email:** first `@`-containing token.
5. **Adjuster phone:** first US-format number.
6. **Adjuster name:** requires explicit `Examiner: Name` / `Adjuster: Name` / `Appraiser: Name` label — avoids false matches on free-form text.

### Auth
- Personal Access Token stored in `config/services.php` → `asana.token` (read from `ASANA_TOKEN` env var). **Not hardcoded** — was hardcoded in v1 and removed for GitHub push, now env-only.
- Project GID for Claims: `1203511376320076` (verified in `ImportAsanaData.php`)

## 🟡 Known limits

- 27 tasks had notes matching a customer name that doesn't exist in `customers` — those claims couldn't be linked and were skipped.
- 15 claim-number-only rows are stored as "Unknown" insurer. Expanding the whitelist further is an option but not likely to yield much more than it already has.
- Adjuster extraction is near-ceiling — the source data simply doesn't have more.

## How to re-run if needed

```bash
# Dry run first (no writes)
php artisan backfill:claim-details --dry-run --limit=20

# Full run
php artisan backfill:claim-details

# Larger carrier whitelist → edit $insurers in BackfillAsanaClaimDetails.php
```

---
Files:
- `app/Console/Commands/BackfillAsanaClaimDetails.php`
- `app/Console/Commands/ImportAsanaData.php` (original import)
- `app/Models/Claim.php` + `app/Models/ClaimInsuranceEntry.php`
