# digipro POS — Production (Phase A)

## Environment

Set on the server (never commit secrets):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
# ... database credentials

QUEUE_CONNECTION=database
# Prefer redis in high traffic: QUEUE_CONNECTION=redis

CACHE_STORE=redis
SESSION_DRIVER=redis

FILESYSTEM_DISK=public
# Or s3 for product images:
# FILESYSTEM_DISK=s3
# AWS_* variables

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

Run `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` after deploy.

## Build & migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
```

## Queue worker

Sales sync and mail need a worker:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Use Supervisor or Laravel Cloud background processes so the worker restarts automatically.

## Scheduler

Add to crontab (single server):

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Backups

- **Database:** daily automated dump (host snapshot or `mysqldump`) retained 7–30 days.
- **Files:** sync `storage/app/public` (product images) with object storage or off-site backup.

## Health

- HTTP: `GET /up` (Laravel health route).
- Monitor 5xx rate, queue depth, disk space.

## Phase B (commercial)

- **Email:** configure SMTP; set `REQUIRE_EMAIL_VERIFICATION=true` in production.
- **Plan payments:** set `BILLING_SANDBOX_CHECKOUT=false` in production; configure `SSLCOMMERZ_*` for Bangladesh card/mobile wallet checkout, or use manual plan assignment from super admin.
- **POS:** bKash is available as a payment method label on completed sales (record-keeping; not a live bKash API integration yet).

## Optional monitoring

- Error tracking: configure Sentry or similar and set `LOG_LEVEL=error` in production.
- Rate limits: auth routes use the `auth` limiter (10/min per IP).

## Capacitor / mobile

Set `VITE_API_URL` to the public site root before `npm run build` for native apps.
