# AutoGo — Established Rules & Decisions (DO NOT REGRESS)

This file records every decision the team has locked in. **Enhance freely, but never undo a point below without explicit user approval in the chat.** Read this before starting any work that touches the listed areas.

---

## Engineering rules (project-wide)

1. **Verified-only documentation** (CLAUDE.md, top priority) — every claim about an external system is labeled ✅ VERIFIED, 🟡 ASSUMED, or ❌ NOT INTEGRATED. Never make up endpoints, command names, library APIs, or vendor behaviors.
2. **PR workflow is mandatory** — every change goes through a feature branch + PR + CI green before merge. No direct pushes to `main`.
3. **CI gates the deploy** — `.github/workflows/ci.yml` runs on every PR: PHP lint, Vite build, prod-bootstrap (artisan caches), PHPUnit against Postgres. All four must pass.
4. **Deploy is idempotent** — `php artisan queue:restart || true` and `supervisorctl restart || true` so a benign restart hiccup never fails the deploy. `bootstrap/cache/*.php` is excluded from rsync; cache rebuilds from prod `.env` on every deploy.
5. **PostgreSQL only** — `ilike` for case-insensitive `LIKE`, `JSONB` for JSON columns.
6. **Money is `decimal:2`** on the model casts. Never store as float.
7. **Audit-logged tables are append-only** — `agreement_revisions`, `audit_logs`, `signatures` block UPDATE/DELETE in `booted()` hooks. Don't bypass.

## SMS bot — copy + behavior

8. **Strict trigger words only** to start a new conversation: `help / new / car / menu / options / start / lease / rental / rent / tow / towing / bodyshop / body / collision / finance / financing`. Anything else from a known customer with no active session = silent.
9. **Trigger words override an active session** — texting `lease` mid-rental flow aborts the rental and starts the lease. Lets customers escape stuck flows.
10. **Customer Show "📱 Text Application" button** bypasses trigger gate (staff-initiated).
11. **No "Reply STOP" advertised in copy** — STOP works as a keyword but it's not appended to messages (annoying).
12. **Never promise pricing, availability, approval odds, or timing** — even ranges, even "usually". This applies to AI prompts AND hardcoded copy.
13. **Towing intro warns 911** — *"⚠️ If this is a medical or roadway emergency, call 911 first."*
14. **SSN over SMS** — masked on display (`***-**-1234`), customer can text `SECURE` to opt out → bot pauses session and notifies staff to call.
15. **Bot finalize uses `Deal::generateDealNumber()`** — never raw insert without the auto-number.

## SMS bot — safeguards (all must remain)

16. **Kill switches via Settings DB** — `bot_disabled`, `ai_router_disabled`, `ai_validator_disabled`, `ai_agent_disabled`. Each toggleable from `/settings` UI.
17. **Same-message rate limit** — if we sent the **same body** 3+ times in 10 min, suppress. Different prompts in a normal flow are NOT capped.
18. **Auto-responder fingerprint** — body contains "auto-reply", "do not reply", "noreply", "out of office", "msg&data rates", "this mailbox is not monitored", etc. → silently ignore.
19. **Bot never goes silent on a real customer message** — every handler wrapped in try/catch with a guaranteed fallback ack: *"Got your message — a team member will reach out shortly."* Then aborts the session so staff sees it.
20. **AI agent universal handoff triggers** (every flow): hostile/sarcastic, "real person", cancel/dispute/complain, mentions injury/accident/lawyer, 2 unanswered messages, customer repeats themselves. **Per-flow:** lease/finance — any APR/program/payoff/credit-decision question → handoff. Bodyshop — specific cost/timeline/coverage. Rental — specific availability/pricing. Towing — ANY pushback at all.
21. **Handoff replies are honest + committal** — "Got it — I'm having a dispatcher call you right now" — not "OK".
22. **Bot chase stalled sessions** — `bot:chase-stalled` artisan, hourly, ONE nudge per session after 4h silence, gives up after 3 days. Tracked via `__chased_at__` in collected.

## SMS — UX rules

23. **Resolve is manual + auto-unresolves on new inbound** — customer texting back is a clear "not done" signal.
24. **Inbox sidebar count = assigned-to-me OR unassigned** — unclaimed conversations ping everyone until someone takes ownership.
25. **Inbox shows ALL conversations** — no row-count cap (was 500, removed).
26. **Inbox assignee badge picks ANY non-null `assigned_to`** in the conversation, not just the most-recent log row.
27. **Resolved row = dimmed only (not strike-through)** + "✓ DONE" outlined pill (visually distinct from green unread badge).
28. **Outbound customer attribution** uses `to` (recipient) for outbound, `from` (sender) for inbound. Looking up by `from` on outbound matches whichever customer wrongly has our business line stored.
29. **Confirm-existing → "what to update" flow** — when customer says NO, bot asks what to fix and only re-collects that field via `__update_<field>__` queue, never wipes everything.

## Telebroad — verified facts

30. **MMS format** (sniffed live from their web UI 2026-04-22): `Content-Type: application/json`, `media` is a **JSON-encoded string** of `[{"name":"foo.png","value":"<raw-base64>"}]`. NOT form-urlencoded, NOT URL strings, NOT `{url, type}`. Anything else delivers text only and silently strips media.
31. **Voice notes** must be **MP3** with filename pattern `voice_note_MM_DD_YYYY_HH_MM_SS.mp3` for phones to display the proper voice-note bubble. Browser webm → ffmpeg → MP3 (mono 44.1kHz 64kbps via libmp3lame).
32. **Images > 500KB get stripped by carriers** — every uploaded image runs through `ImageResizer::fitForMms()` (GD): scales to 1600px max, JPEG quality 85→45 progressively until under 500KB.
33. **Webhook payload field names** — `id`, `direction` (received|sent), `startTime`, `fromNumber`, `toNumber`, `message`, `media`, `webhookType`, `secret`. Verified live; documented in `TelebroadWebhookController` docblock.
34. **Webhook URL format**: `https://app.autogoco.com/api/telebroad/webhook/sms/Account-SMS?secret=<TELEBROAD_WEBHOOK_SECRET>`. Route accepts an optional suffix segment per Telebroad's convention.
35. **Outbound webhook echoes** are skipped only if the message id matches an existing log (prevents dups from our own SmsController) OR an identical body was sent in the last 15s — anything else (Telebroad UI, mobile app, another device) IS imported so the thread is complete.
36. **Scheduled `telebroad:sync` every 10 min** pulls anything webhooks may have missed (SMS conversations + call history). Deduped by external_ref.

## AI — Anthropic SDK

37. **Models in use**: `claude-haiku-4-5` (validator + cheap calls), `claude-sonnet-4-5` (router + agent + OCR). The `claude-3-5-*-latest` aliases are RETIRED — never reintroduce.
38. **Single AiClient wrapper** (`app/Services/AiClient.php`) — all call sites go through it. No direct `Anthropic\Client` calls scattered around the app.
39. **AI is configurable from `/settings`** — Anthropic API key + per-feature toggles (router/validator/agent) + model overrides.
40. **AI router and validator results are cached** — router 60s per (phone, body), validator 15s per (step, answer). Avoid token spam on retries.

## Calendar (Rental)

41. **Pendings are round-robin distributed 1-per-lane** — never overlay every vehicle of the matching class. Hover popover lists all pendings for that class with click-to-assign.
42. **Composite indexes on reservations(pickup_date, return_date)** + status/vehicle_id/vehicle_class — keep them.
43. **Calendar query SELECT is slim** — only the columns the UI actually renders + minimal eager loads. Don't fatten back up.

## Reservations

44. **Agreement PDF generation is async** via `GenerateAgreementSnapshot` queue job. Reservation create writes the row in ~50-100ms and returns immediately. Snapshot runs in the background.
45. **Snapshot only fires on real state transitions** — pickup, return, vehicle_assigned, status change. NOT on bare create (PDF would be incomplete anyway).
46. **Manual "📸 Tamper-Evident Snapshot" button** on Reservation Show page for staff-triggered regenerate.
47. **FPM `memory_limit = 1024M`** on prod (`/etc/php/8.4/fpm/php.ini`). DomPDF needs the headroom.

## Customers

48. **Multi-phone via `customer_phones` table** — primary phone synced to legacy `customers.phone` column for backwards compat. Inbound SMS auto-link uses `Customer::findByAnyPhone($number)` which matches across all phones.
49. **Customer search uses `Customer::scopeSearch()`** — single source of truth used by index, typeahead, deals/claims/reservations/EZpass/lease-docs/rental-claims/SMS-conversations search. Multi-token + concat-name + per-token AND.
50. **Tokenized cards via `customer_cards`** — Cardknox xToken + brand + last4 + exp only; **PAN never persisted**. PAN flows through `SolaPaymentsService::saveCard` once, then dies. iFields integration (PCI-safe iframe entry) is planned-not-built.

## Settings

51. **Secret values never echoed back to the UI** — `/settings` shows green badge "✓ saved · ●●●●1234" instead. Saving a blank value on a *_password / *_secret / *_key / *_token / *_xkey field is a no-op (won't wipe the saved value).
52. **All test buttons fall back through input → DB Setting → .env** via `tval()` helper.
53. **Plain-English ON/OFF toggles** for boolean settings (kill switches), not "set to 1" text fields. `invert: true` flag flips display so On = good when storing `*_disabled` keys.
54. **S3/Contabo support** — endpoint URL field separate from bucket; auto-splits a pasted full URL; region defaults to `us-east-1` so the AWS SDK boots.

## Permissions

55. **Permission types** are page-based — admin creates types, picks pages + actions (view/create/edit/delete) per type, assigns type to user. Spatie sits underneath. Don't replace.

---

## Adding to this file

Any new rule shipped from a chat decision MUST be appended here in the same session. If you (future agent) ship something that contradicts an item above, you owe an explicit explanation in the commit message and a chat note to the user.
