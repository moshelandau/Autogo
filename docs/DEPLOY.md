# Deployment

> **Status:** ✅ verified — many successful runs.

## Pipeline

```
git push origin main
    ↓
GitHub Actions (.github/workflows/deploy.yml)
    ↓
composer install --no-dev + npm ci + npm run build
    ↓
rsync to root@217.216.91.93:/var/www/autogo/
    ↓
ssh exec: chown www-data, chmod storage, php artisan migrate --force,
         optimize:clear, config:cache, route:cache, view:cache, event:cache,
         systemctl restart php8.4-fpm, supervisorctl restart autogo-worker:*
    ↓
curl https://app.autogoco.com/login → must return 200 (else fail)
```

End-to-end time: ~40-60 seconds.

## Production server

- Host: `217.216.91.93` · `app.autogoco.com` (Cloudflare proxied)
- OS: Ubuntu 24.04
- Stack: PHP 8.4.18 · PostgreSQL 18.3 · nginx · Redis · Supervisor
- TLS: Let's Encrypt cert at `/etc/letsencrypt/live/app.autogoco.com/`, auto-renewed by certbot
- Document root: `/var/www/autogo/public`
- DB: `autogo` (PostgreSQL, owned by `autogo` role)
- App key + DB password live in `/var/www/autogo/.env` (NOT in git)

## Required GitHub secrets

| Secret | Purpose |
|---|---|
| `DEPLOY_SSH_KEY` | ED25519 private key authorized in `root@217.216.91.93:~/.ssh/authorized_keys` |

## Manual trigger (e.g. from GitHub mobile app)

The workflow declares `workflow_dispatch:` so it can be run on-demand from:
- GitHub web → repo → Actions → "Deploy to Production" → Run workflow
- GitHub mobile app → repo → Actions → run

## Scheduled jobs on production (verified via `php artisan schedule:list`)

```
0 *  * * *     php artisan sync:towbook                    Hourly
0 9  * * 1,4   php artisan tasks:open-violations-check     Mon + Thu @ 9 AM ET
```

## Workers (verified via `supervisorctl status`)

`autogo-worker:autogo-worker_00` and `autogo-worker_01` — 2 queue workers running `php artisan queue:work`. Auto-restart on failure. Restarted on every deploy.

## Cron entry on the server

```cron
* * * * * cd /var/www/autogo && php artisan schedule:run >> /dev/null 2>&1
```

This is what makes the scheduled jobs above actually fire.

## Health check fail behavior

If the post-deploy curl to `/login` doesn't return 200, the GitHub Actions job fails and is reported in the email/web notification. The deploy already completed by that point — health check is a smoke test only, not a rollback.

---
Files:
- `.github/workflows/deploy.yml`
- `/etc/nginx/sites-enabled/autogo` (server)
- `/etc/supervisor/conf.d/autogo-worker.conf` (server)
- root crontab on the server
