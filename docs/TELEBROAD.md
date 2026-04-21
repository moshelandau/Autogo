# Telebroad

> **Status:** 🟡 **partially integrated** — outbound SMS via `POST /send/sms` is wired in (`SmsController` + `SmsButton.vue`); call/recording/history endpoints still stubbed. Base host is still **assumed** (`https://webserv.telebroad.com/api/teleconsole/rest`) — needs confirmation against a real auth pair.

## ✅ Inbound SMS — webhook receiver (built; needs live test fire)

- Telebroad supports an **Account-SMS webhook** that fires for every SMS sent or received on the account — VERIFIED via [helpdesk article 4000214102](https://helpdesk.telebroad.com/support/solutions/articles/4000214102-webhook-integrations).
- Configure in the Telebroad portal with this URL (append the trigger name `/Account-SMS` per Telebroad's convention):
  ```
  https://app.autogoco.com/api/telebroad/webhook/sms?secret=<TELEBROAD_WEBHOOK_SECRET>/Account-SMS
  ```
- AutoGo wiring:
  - `App\Http\Controllers\TelebroadWebhookController@sms`
  - Route: `POST /api/telebroad/webhook/sms` (CSRF-exempt, secret-gated)
  - Auto-links inbound SMS to a Customer by matching the last 10 digits of the `from` number against `customers.phone`
  - Stores raw payload under `attachments._raw` for forensics
  - Sends an `OperationalReminder` notification to all `@autogoco.com` users
- ✅ **VERIFIED webhook payload** (captured from real fire 2026-04-21):
  ```json
  {
    "id": 110167431,
    "direction": "received",        // or "sent" for outbound
    "startTime": "2026-04-21T18:47:50-04:00",
    "fromNumber": "18455008085",
    "toNumber":   "18457511133",
    "message":    "3rd",
    "media":      null,
    "webhookType":"AccountSMS",
    "secret":     "<your shared secret, echoed back>"
  }
  ```
  Telebroad echoes the `?secret=…` query value back inside the body too — handy belt-and-suspenders.

## ✅ Conversation UI

- `/sms` — inbox listing one row per phone number, unread counts, click-through to thread
- `/sms/thread/{phone}` — bubble-style thread with inline reply
- Customer Show → "Messages" tab — embedded thread + reply for that customer
- All inbound `received` rows are flipped to `read` on viewing the thread

## ✅ Outbound SMS (built — needs live-credential test)

- Endpoint: `POST /send/sms` — **VERIFIED** via [helpdesk article 4000110801](https://helpdesk.telebroad.com/support/solutions/articles/4000110801-post-send-sms)
- Verified body params: `sms_line` (your Telebroad SMS line, E.164), `receiver` (recipient, E.164), `msgdata` (text body), optional `media` (JSON array, base64) for MMS
- Auth: HTTP Basic (Telebroad username + password) — VERIFIED
- Pricing reference: $0.0125 per SMS segment, $0.03 per MMS
- AutoGo wiring:
  - `App\Services\TelebroadService::sendSms($to, $message)`
  - `POST /sms/send` route → `SmsController@send`
  - `<SmsButton>` Vue component (Customer Show, Deal Show)
  - Every send writes a row to `communication_logs` (channel=`sms`, direction=`outbound`)

## ✅ Verified facts

### REST API
Telebroad publishes a REST API documented at their help-desk article on `helpdesk.telebroad.com`. It includes (verified count from that page):

- **29 GET endpoints** — examples:
  - `GET /active/calls` — currently active calls
  - `GET /call/flow` — call flow info
  - `GET /call/history` — call history
  - `GET /call/recording` — call recordings
- **8 POST endpoints** — examples:
  - `POST /call/hangup` — hang up a call
  - `POST /call/pickup` — pick up an incoming call
  - `POST /call/redirect` — transfer / redirect
  - **`POST /send/call`** — **server-initiated click-to-call** (rings your Telebroad extension, then dials the target number and bridges)
- **8 PUT endpoints** — e.g. `PUT /contact`, `PUT /myStatus`
- **8 DELETE endpoints** — e.g. `DELETE /phone/cdrs`

### What this means for AutoGo

| Feature | Feasible? | How |
|---|---|---|
| **Click-to-call from any customer/reservation page** | ✅ yes | `POST /send/call` — we send customer's number; Telebroad rings the operator's extension first, then dials out and bridges |
| **Pull call history into `communication_logs`** | ✅ yes | Scheduled `GET /call/history` → insert rows |
| **Attach call recordings to a rental/claim** | ✅ yes | `GET /call/recording` by call id |
| **Auto-match inbound caller-ID to customer on screen pop** | 🟡 possible | Requires webhooks OR polling `GET /active/calls` — not yet verified whether webhooks are supported |
| **Desktop softphone (Zoiper / Linphone / Bria) registered to your Telebroad line** | ✅ **yes** | See "Softphone (SIP)" below |
| **Browser softphone (WebRTC inside AutoGo)** | 🟡 **uncertain** | See "Browser softphone (WebRTC)" below |

## ✅ Softphone (SIP) — verified from helpdesk article 4000142010

Telebroad publishes settings to register a SIP softphone (Zoiper, Linphone, Bria, etc.):

- **Transport:** SIP **TLS** (recommended)
- **Username:** the PBX extension number (e.g. `113842`) — same as the extension you'd dial internally
- **Authentication Username:** not needed
- **Outbound Proxy:** not needed
- **SIP server / domain:** **fetched via `GET /myProfile` REST call** — Telebroad doesn't publish a single fixed hostname; the per-account server is returned by that API
- **Password:** the SIP password from the same `/myProfile` response

**Conclusion:** A workstation softphone (Zoiper/Linphone/Bria) is fully supported and easy to set up.

## 🟡 Browser softphone (WebRTC inside AutoGo) — still unconfirmed

- SIP TLS is what desktop softphones speak. Browsers cannot make raw SIP TLS — they need **SIP-over-WebSocket (WSS)**.
- The Telebroad help articles surfaced so far do NOT mention WSS, WebRTC, STUN/TURN, or a browser softphone SDK.
- **To confirm:** ask Telebroad support directly *"Do you provide a SIP-over-WebSocket endpoint for browser softphones (WebRTC)? If yes, what's the WSS URL and any STUN/TURN config?"*
  - If **yes** → we build a JsSIP / SIP.js softphone widget in AutoGo
  - If **no** → operators install a desktop softphone, AND AutoGo's REST-based click-to-call (`POST /send/call`) handles browser-initiated calls (rings the desktop softphone, then bridges to the customer)

## ❌ Still not verified for our REST API code

- **Auth method** — not specified on the help-desk index. Likely the SIP credentials, or a separate API token issued from `GET /myProfile` — **need a real test call to confirm**.
- **Exact base URL of the REST API** — we have endpoint paths (`/myProfile`, `/send/call`, etc.) but not the host. Current config guesses `https://webserv.telebroad.com/api/teleconsole/rest`. **Needs confirmation.**

## 🟡 Current AutoGo code

- `config/services.php` has `telebroad.username`, `telebroad.password`, `telebroad.phone_number`, `telebroad.api_url`
- `Settings → Telebroad` UI accepts those values
- **Test button is a stub** — it calls `/extensions` which is not in the verified endpoint list and will likely fail.

## Next steps (blocked on real credentials)

1. Ask Telebroad support / portal for:
   - Real REST API base URL
   - Auth method (Basic? token?)
   - Whether webhooks are supported and how to register them
2. Ship a pilot click-to-call button wired to `POST /send/call`
3. Add a scheduled job to pull `GET /call/history` hourly into `communication_logs`
4. When that works, add recording playback via `GET /call/recording`

---
Sources:
- [Telebroad REST API index (helpdesk solution 4000005985)](https://helpdesk.telebroad.com/support/solutions/4000005985)
- [Telebroad API landing page](https://www.telebroad.com/api)
