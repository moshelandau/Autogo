# AutoGo Documentation

> **Documentation rule:** every claim in this folder is either:
>   ✅ **VERIFIED** — confirmed by official docs, a screenshot the user shared, or a passing test against the real service
>   🟡 **ASSUMED** — written based on convention or memory; explicitly marked, MUST be confirmed before relying on it
>   ❌ **NOT YET INTEGRATED** — credentials/access not yet available, code is a stub
>
> If something is in this folder without one of those three labels, it's a bug — please flag it.

---

## Integrations status

| Integration | Status | Doc |
|---|---|---|
| Cardknox (Sola Payments) | 🟡 partially verified | [CARDKNOX.md](CARDKNOX.md) |
| EZ Pass NY (CSV import) | ✅ verified against actual export | [EZPASS.md](EZPASS.md) |
| TowBook | ❌ no API access yet | [TOWBOOK.md](TOWBOOK.md) |
| HQ Rentals | ❌ no API access yet | [HQ_RENTALS.md](HQ_RENTALS.md) |
| Telebroad | ❌ no integration built | [TELEBROAD.md](TELEBROAD.md) |
| Asana (claims backfill) | ✅ verified — pulled 393 entries | [ASANA.md](ASANA.md) |
| 700Credit | ❌ no API key yet | [CREDIT700.md](CREDIT700.md) |
| Anthropic (vision OCR) | ✅ verified | [ANTHROPIC.md](ANTHROPIC.md) |

## In-app systems

| System | Status | Doc |
|---|---|---|
| Rental Agreement PDF | ✅ matches user-supplied template | [RENTAL_AGREEMENT.md](RENTAL_AGREEMENT.md) |
| Evidence (revisions + audit + signatures + comms) | ✅ tested in dev | [EVIDENCE_SYSTEM.md](EVIDENCE_SYSTEM.md) |
| Recurring violations-check task (Mon+Thu) | ✅ verified scheduler entry | [VIOLATIONS_CHECK_TASK.md](VIOLATIONS_CHECK_TASK.md) |
| Vehicle Violations | ✅ tested in dev | [VIOLATIONS.md](VIOLATIONS.md) |
| GitHub Actions auto-deploy | ✅ verified — many successful runs | [DEPLOY.md](DEPLOY.md) |
