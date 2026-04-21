# Cardknox / Sola Payments

> **Status:** 🟡 partially verified · endpoint verified by user screenshot · command strings verified against [docs.solapayments.com](https://docs.solapayments.com/api/transaction/credit-card) · **actual xKey-authenticated call not yet executed against production**.

## ✅ Verified facts

### Same product, two names
- **Sola Payments is white-labeled Cardknox.** `docs.cardknox.com` redirects to `docs.solapayments.com`.
- Kb article `kb.cardknox.com/api/` also redirects to the docs site.

### Gateway endpoint
- `https://x1.cardknox.com/gatewayjson` — verified from the user's Derech/Shvil Settings screenshot.

### Auth
- Per-merchant `xKey` field. Each merchant (AutoGo, High Car Rental) has its own xKey. Verified from screenshot.

### Verified `xCommand` strings (from official docs + web search of docs)
| Command | Purpose |
|---|---|
| `cc:sale` | One-step authorize + capture (charge now) |
| `cc:authonly` | Authorize only — **use for $250 security deposit hold** |
| `cc:capture` | Capture a prior `cc:authonly` by `xRefNum` |
| `cc:voidrelease` | **Release (void) a prior authorization** — use when rental returns clean |
| `cc:voidrefund` | Void + refund a captured sale (different from voidrelease) |
| `cc:refund` | Refund an original sale by `xRefNum` |
| `cc:void` | Void a same-day captured transaction before batch |
| `cc:save` | Tokenize a card without charging (store as card-on-file) |
| `cc:adjust` | Adjust pending transaction amount |
| `cc:postauth` | Submit an external auth for capture |
| `cc:credit` | Standalone credit (no prior sale) |
| `cc:avsonly` | AVS check without charging |

### Required request fields (form-encoded POST)
- `xKey` (merchant key)
- `xVersion` (e.g. `5.0.0`)
- `xSoftwareName` (e.g. `AutoGo`)
- `xSoftwareVersion`
- `xCommand` (one of the above)
- `xAmount` (for sale/auth)
- Card data either as:
  - `xToken` (PCI-safe, for a previously-saved card), OR
  - `xCardNum`, `xExp` (MMYY), `xCVV`

### Required response fields
- `xStatus` — `Approved` on success
- `xError` — message on failure
- `xRefNum` — reference # (save this for later capture/void/refund)
- `xToken` — returned on `cc:save`; persist this (never the PAN)

## 🟡 Our implementation

### File: `app/Services/SolaPaymentsService.php`

Methods and the verified command each uses:
| Method | `xCommand` sent | Notes |
|---|---|---|
| `authorizeHold($card, $amount)` | `cc:authonly` | Always routes to High Rental merchant |
| `captureHold($hold, $amount)` | `cc:capture` | Uses `xRefNum` from the auth |
| `releaseHold($hold)` | `cc:voidrelease` | Correct command for releasing untouched auths |
| `charge($account, $card, $amount)` | `cc:sale` | Operator picks AutoGo vs High Rental per charge |
| `test($account)` | `cc:save` with no card | Works because bad xKey → auth error; good xKey → missing-card error. No actual card/charge side-effects. |

### Mock mode
If no xKey is configured for an account, the service returns mock responses so the UI can be tested end-to-end during development. Mock responses are clearly marked with `'mock' => true`.

## ❌ Not yet verified — MUST test before trusting

- The exact `xError` string returned by bad-xKey vs missing-card — our test heuristic assumes "key" / "authentication" / "unauthorized" / "invalid login" substrings appear for auth failures. **First real test against prod will either confirm or force an update to `test()` in the service.**
- Live charge against a real test card — once we have sandbox keys, put through a $1 `cc:sale`, a $250 `cc:authonly` + `cc:capture`, and a `cc:authonly` + `cc:voidrelease` and record actual responses.
- Cardknox **iFields** (PCI-safe iframe card entry) integration — planned, not yet built. Required for PCI compliance when operators type in card data.

## Testing steps (run these the first time a real xKey is entered)

1. Enter AutoGo xKey → click **Test AutoGo xKey** → expect "✓ AutoGo xKey accepted by Cardknox".
2. Enter a garbage string → click Test → expect "✗ xKey rejected".
3. On a real rental: complete pickup → verify a `cc:authonly` went through → check ReservationHold row + card's statement showing a pending auth of $250.
4. Complete return → click **Release Now (clean)** → verify `cc:voidrelease` + pending auth disappears from card.
5. Separately: start a POS charge → pick AutoGo account → verify `cc:sale` goes through on the AutoGo merchant (not High Rental).

---
Sources:
- [Sola/Cardknox Credit Card docs](https://docs.solapayments.com/api/transaction/credit-card)
- [Cardknox Code Samples](https://docs.solapayments.com/api/code-samples)
- [Transaction API overview](https://docs.solapayments.com/api/transaction)
