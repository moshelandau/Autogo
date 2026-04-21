# 700Credit

> **Status:** ❌ **API not verified.** The service stub exists; it has **not** been called against a real 700Credit endpoint. Mock mode returns fake scores for UI development.

## Business rule (verified from user)

- **AutoGo only performs SOFT PULLS.** Hard pulls are performed by the dealer / lender after the application is submitted — not by AutoGo.
- This is enforced in the code: `Credit700Service::hardPull()` was removed; `CreditPullController::store()` accepts no `type` / no SSN / no consent field. Only a soft-pull path exists.

## ❌ NOT verified

### API endpoint, auth, and request/response shape
Our code assumes:
- Base URL: `https://api.700credit.com/v1` (from `CREDIT700_API_URL` env; default is a guess)
- Auth: `Bearer {api_key}`
- Endpoint: `POST /soft-pull`
- Request body fields: `first_name, last_name, date_of_birth, address, city, state, zip`
- Response fields: `credit_score, score_model, bureau`

**All of the above are guesses.** 700Credit's actual API shape is unknown to us.

### Before using in production

1. Contact 700Credit / provide dealer credentials
2. Request API docs + sandbox key
3. Correct `App\Services\Credit700Service::softPull()` based on real response shape
4. Run one test pull in sandbox; verify a real score comes back and gets stored on a `CreditPull` row

### Pricing context (per user)
Soft pull: ~$5, no SSN, no impact on consumer score, result cached 30 days.

## Mock mode

When `CREDIT700_API_KEY` is empty, `softPull()` returns a mock response with a random score between 580-820 and sets `full_report = ['mock' => true, ...]`. This lets the UI + customer-page workflow be developed without real API access.

---
Files:
- `app/Services/Credit700Service.php`
- `app/Http/Controllers/CreditPullController.php`
- `config/services.php` → `credit700`
