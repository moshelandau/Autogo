# Anthropic (Claude vision + OCR)

> **Status:** ✅ **Used + working in a related project (Motel).** Ported to AutoGo for document auto-classification on the Plustek scan flow. **Not yet exercised against production AutoGo traffic** because the Plustek scanner isn't physically connected to a dev machine yet.

## ✅ Verified

### SDK
Using `anthropic-ai/sdk` PHP package (v0.16.0 at time of install). This is the official Anthropic PHP SDK.

### What we use it for in AutoGo

**Customer document scan** (`app/Http/Controllers/CustomerScanController.php`):
- When the Plustek PS186 scanner feeds a page, we receive base64 JPEG
- Send to Claude with prompt: *"Classify this scanned document. Output JSON: { type, fields }. Type is one of [drivers_license_front, drivers_license_back, passport, insurance_card, registration, proof_of_residence, paystub, w2, utility_bill, lease_agreement, credit_card, other]."*
- Extract fields: `name, dl_number, expiration, state, insurance_company, policy_number, card_brand, card_last4`
- Auto-fill empty customer fields (DL #, DL state, DL exp, insurance company, policy #)

Model used: `claude-3-5-sonnet-latest` (user can upgrade — model name is in service code).

## 🟡 Not yet verified in AutoGo

- End-to-end scan → classify flow has not been tested against the real Plustek device in this codebase (the Motel project it was ported from has this working).
- Anthropic's vision responses can occasionally wrap JSON in markdown code fences — our parser strips ```json fences, but edge cases may remain.

## Config

```ini
ANTHROPIC_API_KEY=sk-ant-api03-...
```

`config/services.php` → `anthropic.api_key`.

The service degrades gracefully: if no key is set, scan documents are saved but auto-classification is skipped — operator picks the type manually.

---
Files:
- `app/Http/Controllers/CustomerScanController.php`
- `resources/js/Pages/Customers/Scan.vue`
- `public/assets/plustek/scan.js` (ported verbatim from the Motel project — 2658 lines)
