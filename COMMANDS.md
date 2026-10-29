# digipro POS — Commands

Commands to run, update, test, and deploy this app.  
Production details: [DEPLOY.md](DEPLOY.md) · Mobile apps: [MOBILE.md](MOBILE.md)

Project folder:

```bash
cd /Users/samiul/Digi-pro-soft/digipro-POS
```

---

## 1. Local (this Mac) — Laravel Sail

If `.env` has `DB_HOST=mysql`, run Artisan **inside Sail**. Running `php artisan migrate` on the Mac itself fails with `getaddrinfo for mysql failed`.

### First-time setup

```bash
composer install
cp .env.example .env          # only if .env is missing
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan db:seed --force
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

App URL for this project: **http://localhost:8088**  
phpMyAdmin (if enabled in Sail): port `FORWARD_PHPMYADMIN_PORT` (for example 8091)

### Start / stop daily

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev      # Vue/CSS hot reload
./vendor/bin/sail stop
```

All-in-one (Vite + queue + logs):

```bash
composer run dev
```

Sail must be running. `composer run dev` uses the Mac `php artisan` binary. If `DB_HOST=mysql`, start Sail first, or run Artisan only through Sail:

```bash
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan db:seed --force
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan test --compact
```

### Restore local dev packages

`composer install --no-dev` removes Sail, Pint, and PHPUnit. On this machine run:

```bash
composer install
```

---

## 2. Super admin (after seed)

| | |
|---|---|
| URL | `/login` |
| Email | `superadmin@digipro.test` |
| Password | `password` |

Change this password immediately in production.

- Self-serve store signup: `/register` (trial length is set under **Admin → Plans**)
- Super admin creates a store: **Admin → Stores → Add store**
- Trial length: **Admin → Plans** (Trial days at the top)

---

## 3. Frontend build

After UI changes, or before uploading to production:

```bash
npm ci
npm run build
```

`public/build` is gitignored. Upload that folder to the server separately. Without it the site returns 500 (`Unable to locate file in Vite manifest`).

---

## 4. Tests

```bash
composer install
php artisan test --compact
```

Via Sail:

```bash
./vendor/bin/sail artisan test --compact
```

Code style:

```bash
vendor/bin/pint --dirty
```

---

## 5. Git (DigiPro account)

Remote for this repo: `git@github.com-digipro:digiprosoftofficial-coder/digi-pos.git`

```bash
git status
git add .
git commit -m "your message"
git push origin main
```

Check SSH:

```bash
ssh -T git@github.com-digipro
```

Never commit `.env`.

---

## 6. Production (shared hosting / cPanel)

**Do not run migrate on the Mac.** Use the server Terminal:

```bash
cd ~/softopos.digiprosoft.com

php -v                              # 8.3 or 8.4
composer install --no-dev --optimize-autoloader
php artisan key:generate --force    # first deploy only, if APP_KEY is empty
php artisan migrate --force
php artisan db:seed --force         # first deploy only (super admin + plans)
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If Composer is missing or you get a `phar` error, run `composer install --no-dev --optimize-autoloader` on this Mac and upload `vendor/`. **Do not run `composer update`.**

After a code change:

1. Run `npm run build` on this Mac
2. Upload new PHP/Vue files, `public/build`, and `composer.lock`
3. On the server:

```bash
cd ~/softopos.digiprosoft.com
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

cPanel `.env` (do not share passwords in chat):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://softopos.digiprosoft.com
DB_CONNECTION=mysql
DB_HOST=localhost
FILESYSTEM_DISK=public
LOG_LEVEL=error
```

Queue (cron, every minute):

```cron
* * * * * cd /home/USER/softopos.digiprosoft.com && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Health check: `https://your-domain.com/up`

Document root should be `.../softopos.digiprosoft.com/public`, not the Laravel project root.

---

## 7. Common mistakes

| Command | Where | What goes wrong |
|---|---|---|
| `php artisan migrate` | Mac, `DB_HOST=mysql` | Host `mysql` does not resolve — use Sail |
| `composer install --no-dev` | Mac (dev folder) | Sail/tests disappear — run `composer install` |
| `composer update` | Server | Lockfile drift and PHP version mismatches |
| migrate only | Server | No super admin — also run `db:seed` |
| Upload code without `public/build` | Server | 500 Vite error |

---

## 8. Related files

| Topic | File |
|---|---|
| Production checklist | [DEPLOY.md](DEPLOY.md) |
| Android / iOS | [MOBILE.md](MOBILE.md) |
| Env example | [.env.example](.env.example) |
