# Telebroad

> **Status:** ❌ **not yet integrated.** This document describes only what's verified from Telebroad's public support pages. No code in AutoGo currently calls the Telebroad API; the `Settings → Telebroad` fields exist but the Test button is a stub.

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
| **Browser softphone (click-to-call from laptop audio)** | ❌ **not feasible with documented APIs** | Telebroad's public docs do NOT publish a WebRTC/SIP-over-WebSocket endpoint or JavaScript SDK. Ops must use their desk phone / Telebroad's own app. |

## ❌ Not verified / not available

- **WebRTC SIP-over-WebSocket (WSS) URL** — not documented on public pages
- **JavaScript SDK for browser phone** — none published
- **Auth method** — not specified on the help-desk index page; probably HTTP Basic or token — **need a working account to confirm**
- **Exact base URL of the REST API** — we have the path names but not the host. Our current config has `https://webserv.telebroad.com/api/teleconsole/rest` as a guess — **this needs to be confirmed with Telebroad support or by sniffing a real API call**.

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
