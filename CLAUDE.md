# AutoGo — Engineering Rules for Claude

## Documentation rule (HIGHEST PRIORITY)

**Never make up endpoints, command names, library APIs, or vendor behaviors.** Every claim about an external system must be one of three things:

1. ✅ **VERIFIED** — confirmed by official docs, a screenshot the user shared, or a real call against the live service
2. 🟡 **ASSUMED** — explicitly labeled as a guess; flagged in the code/docs as needing confirmation
3. ❌ **NOT INTEGRATED YET** — write a stub but document clearly that nothing has been tested

If you can't put a label on it, don't write it.

When you write integration code without verifying the API:
- Mark the file/method clearly: `// UNVERIFIED — endpoint guessed; needs confirmation against vendor docs`
- Add the integration's status to `docs/<vendor>.md` honestly
- Don't claim "✓ deployed" or "tested" or "working" in commit messages or chat unless you actually ran it

## Project layout

- Laravel 12 + Inertia + Vue 3 + Tailwind
- PostgreSQL 18 (DB), Redis (cache), Supervisor (queue workers)
- Production: `app.autogoco.com` → `217.216.91.93` (Cloudflare proxied)
- Auto-deploy: push to `main` → GitHub Actions → server in ~40s. See `docs/DEPLOY.md`.

## Where things live

- Verified integration docs: `docs/`
- Domain models: `app/Models/`
- Controllers: `app/Http/Controllers/`
- Services (third-party API wrappers): `app/Services/`
- Vue pages: `resources/js/Pages/`
- Vue shared components: `resources/js/Components/`
- Layout/nav: `resources/js/Layouts/AppLayout.vue`
- Migrations: `database/migrations/`
- Routes: `routes/web.php`
- Scheduler / artisan registration: `routes/console.php`
- Middleware registration + trust proxies: `bootstrap/app.php`

## Hard rules

- **Never commit secrets.** Asana token, Cardknox xKey, Anthropic API key, etc. all read from `config/services.php` which reads from `env()`. The `.env` is git-ignored.
- **PostgreSQL only.** Use `ilike` for case-insensitive `LIKE`. Use `JSONB` operators (`->`, `->>`, `whereJsonContains`) for JSON columns.
- **Inertia, not API JSON.** Pages render via `Inertia::render('Path/Component', [...])`. JSON-only endpoints are for axios calls from inside a Vue component (e.g. `CustomerSelect`'s typeahead).
- **Money is `decimal:2`** on the model casts. Never store as float.
- **Audit-logged tables are append-only.** `agreement_revisions`, `audit_logs`, and `signatures` block UPDATE/DELETE in their model `booted()` hook. Don't bypass.
- **Cardknox PCI:** card numbers (`xCardNum`) only flow through the Cardknox service. We never persist the PAN — only `xToken`, `card_brand`, `card_last4`, `card_exp`. iFields integration (PCI-safe iframe entry) is planned, not yet built.

## Build & deploy

```bash
npm run build              # Vite build
php artisan migrate        # apply pending migrations
git push origin main       # triggers auto-deploy via GitHub Actions
```

## When you're not sure

Ask the user. Don't guess at:
- Vendor API URL paths
- Auth methods (Bearer vs Basic vs xKey vs SIP)
- Required field names in third-party POST bodies
- Library function names you can't recall exactly

It's faster and cheaper to ask one question than to ship code that doesn't work and has to be unwound.
