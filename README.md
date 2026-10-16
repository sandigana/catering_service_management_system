# Catering Service Management System

## Purpose

A web app for running a catering business end to end.

- **Customers** browse packages, book events (with a live availability check), pay or upload a payment slip, request a new date, and download invoices and receipts.
- **Staff (back office)** manage bookings, customers, events, payments, inventory and event supplies, and print day sheets.
- **Administrators** do everything staff do, plus manage users, packages and menu items, view reports (CSV export) and the activity log.

It prevents double bookings (daily event limit and venue clashes), tracks balances and payments, reserves stock per event, and sends e-mail/SMS notifications and reminders.

**Stack:** Laravel 12 (PHP 8.2+), Vite + Tailwind, MySQL or PostgreSQL.

---

## Install locally

You need PHP 8.2+, [Composer](https://getcomposer.org), Node.js 20+ and a database (MySQL, e.g. XAMPP, or PostgreSQL).

1. Create an empty database, e.g. `catering_db` (MySQL collation `utf8mb4_unicode_ci`).
2. Install and configure:
   ```bash
   composer install
   cp .env.example .env          # Windows: copy .env.example .env
   php artisan key:generate
   ```
3. Edit `.env`:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (MySQL default: user `root`, empty password).
   - `ADMIN_EMAIL` and `ADMIN_PASSWORD` (8+ characters, letters and numbers). This is your admin login.
   - Keep `MAIL_MAILER=log` and `QUEUE_CONNECTION=sync` for local testing.
4. Create tables, the admin account and the storage link, then build assets and start:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   npm install
   npm run build                 # or `npm run dev` while developing
   php artisan serve
   ```
5. Open **http://127.0.0.1:8000** and sign in with the admin credentials.

For PostgreSQL locally, set `DB_CONNECTION=pgsql`, `DB_PORT=5432` and the usual `DB_*` values.

### Optional: demo data

```bash
php artisan demo:seed     # sample packages, bookings, payments, staff, customers
php artisan demo:clear    # removes all of it (keeps admin accounts)
```

All demo accounts use the password `Demo@12345`: `staff1@demo.test`, `staff2@demo.test`, `customer1@demo.test` ... `customer7@demo.test`. `staff3` and `customer8` are deactivated on purpose (login must be refused). Sent e-mails and SMS are written to `storage/logs/laravel.log` while `MAIL_MAILER=log`.

### Run the tests

```bash
php artisan test
```

---

## Deploy online (Render + PostgreSQL)

The repo includes `Dockerfile`, `docker/` and `render.yaml`, so Render builds everything for you (assets, PHP dependencies, Apache). On every start it runs migrations, creates the admin, and caches config.

1. **Push the project to GitHub** (the `.env` file is git-ignored; never commit it).
2. In Render: **New -> Blueprint**, connect the repo and select it. Render reads `render.yaml` and creates:
   - a Docker web service `catering-system`
   - a PostgreSQL database `catering-db`
3. When prompted, enter **`ADMIN_EMAIL`** and **`ADMIN_PASSWORD`**. These become your first admin login.
4. Click **Apply**. The first build takes a few minutes. When the service shows *Live*, open its `onrender.com` URL and sign in.

`APP_KEY` is generated automatically, `DB_URL` is wired to the database, and `APP_URL` defaults to the Render URL.

### Environment variables

| Variable | Purpose |
|----------|---------|
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | First administrator. Changing them later does **not** reset an existing admin; change the password in the app. |
| `APP_URL` | Only set for a custom domain (`https://catering.example.com`). |
| `MAIL_MAILER` + `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Real e-mail via SMTP (`MAIL_MAILER=smtp`). With the default `log`, e-mails appear only in the service logs. |
| `SMS_DRIVER=semaphore`, `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME` | Real SMS reminders (costs money per message). |
| `CATERING_*` | Business rules, see `config/catering.php` (events per day, reschedule notice, etc.). |

Change values under the service's **Environment** tab; Render redeploys automatically.

### Limits of the free plan (read this)

- **Uploaded files are not kept.** Payment slips and package/menu images are stored on the container disk, which is wiped on every deploy or restart. For real use, switch the service to a paid plan and uncomment the `disk:` block in `render.yaml` (and set `plan: starter`).
- **Free PostgreSQL expires** after Render's trial period; upgrade the database before then or you lose the data.
- **The service sleeps** after inactivity, so the first request can take about a minute.
- **Scheduled jobs do not run** (08:00 reminders, 08:05 low-stock digest, nightly backup) because there is no scheduler process. Add a Render *Cron Job* running `php artisan schedule:run` every minute, or run `php artisan bookings:send-reminders` and `php artisan inventory:low-stock-digest` daily. `db:backup` supports MySQL/SQLite only; on Render use the database's own backups.

### Updating and troubleshooting

- Push to the connected branch; Render rebuilds and migrates automatically.
- Build or boot errors: service **Logs** tab. A missing `ADMIN_PASSWORD` makes the seeder print a random password once in the logs.
- Redirect loops or `http://` links: make sure `TRUSTED_PROXIES` is `*` and `APP_ENV` is `production`.
- Login fails after changing `APP_KEY`: sessions and encrypted data are tied to the key; do not rotate it casually.
- Searching is case-sensitive on PostgreSQL (`LIKE`); see "Known issues".

### Known issues on PostgreSQL

- Search boxes (packages, menu, bookings, users, events, staff, inventory) use `LIKE`, which is case-sensitive on PostgreSQL, so "john" will not match "John". Fixing this means switching those queries to `ilike`.
- `php artisan demo:clear` uses `truncate` with foreign-key checks off, which needs superuser rights on PostgreSQL. Use it locally only.

---

## Roles

| Role | Area | Can do |
|------|------|--------|
| `user` (customer) | `/customer` | browse packages, book, cancel own upcoming booking, pay, view receipts, edit profile |
| `staff` | `/admin` | bookings, customers, events, payments, inventory. No deleting, users, packages, menu, reports or audit log |
| `admin` | `/admin` | everything |

New registrations are always customers.

## Key business rules

- At most `CATERING_MAX_EVENTS_PER_DAY` pending/confirmed bookings per date; the same venue cannot be booked twice within `CATERING_VENUE_SLOT_HOURS` hours.
- Balance = total minus *paid* payments; a payment cannot exceed the balance. Customer slips stay *pending* until staff verify them.
- Passwords need 8+ characters with letters and numbers; login is rate-limited; idle sessions expire after `SESSION_LIFETIME` minutes.

## Production checklist (any host)

`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, a unique `ADMIN_PASSWORD`, real `MAIL_*`, document root pointed at `public/`, and the scheduler cron `* * * * * php artisan schedule:run`. Never commit `.env`.
