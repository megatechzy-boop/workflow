# Dev notes (handover)

Status on 2026-10-29: live at https://workflow.megatechzy.com, commit `b00af00`
(new design, PWA, icons, WhatsApp-style chat, web push, dark mode).

## Set up on a new computer

```bash
git clone https://github.com/megatechzy-boop/workflow.git
cd workflow
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # local DB is SQLite
php artisan migrate --seed
npm run build
php artisan serve
```

Phone push locally: copy the three `VAPID_*` lines from the server `.env`
(`/home/megavtech/web/workflow.megatechzy.com/private/app/.env`) into the local `.env`. Never commit them.

## Deploy (VPS, since 2026-11-28)

- Server: BigRock VPS `64.247.186.254`, HestiaCP (panel `https://server.megatechzy.com:8083`, user `megavtech`),
  Ubuntu 22.04, PHP 8.5, MariaDB. SSH as root with a key.
- App folder: `/home/megavtech/web/workflow.megatechzy.com/private/app` (a git clone of `main`);
  the site's `public_html` is a symlink to `private/app/public`. DB `megavtech_workflow`.
- Deploy = push `main`. A cron runs `private/deploy.sh` every minute: when `origin/main` moved it does
  `artisan down`, `git reset --hard origin/main`, `composer install --no-dev` (only if `composer.lock`
  changed), `migrate --force`, `optimize:clear`, `artisan up`. Log: `private/deploy.log`.
- Build assets locally (`npm run build`) and commit `public/build` (no Node on the server).
- Cron (Hestia > Cron, user `megavtech`): `schedule:run` every minute, plus the deploy script.
- SSL: Let's Encrypt from Hestia. DNS: Cloudflare (all records grey cloud).
- `www.megatechzy.com` (repo `mt_website`) is on the same server with its own `private/deploy.sh`.
- Mail for `megatechzy.com` (incl. `notifications@`, used by `MAIL_HOST=mail.megatechzy.com`) is still on
  the old cPanel (`135.181.219.236`); MX points to `mail.megatechzy.com`, an A record to that IP.
- Old cPanel copy (`/home2/megatechzy/workflow`) is kept in maintenance mode (`storage/framework/down`)
  so its scheduler stays off. `.cpanel.yml` and the `deploy-vendor` branch are no longer used.

## Open items

1. Force notifications: done on `feature/force-notifications` (all roles incl. Admin,
   phone + laptop). Check on a real phone after deploy.
2. Email: cPanel mailbox `notifications@megatechzy.com` (Resend dropped). Put its password in
   server `.env` as `MAIL_PASSWORD`, test with Admin > Users > Send test email.
3. Packages + module 7 built (see CLAUDE.md). Attendance starts on deploy day, so fix the
   first month's days in Admin > Attendance before paying salary.
   Invoices / receipts: bank + UPI only in server `.env` (`BANK_*`, see `.env.example`; the repo
   is public). `INVOICE_START=324`, `RECEIPT_START=39` continue the old Refrens numbers.
   GST later: `BUSINESS_GSTIN` + `BUSINESS_GST_RATE=18`. PDF is made in the browser (html2canvas).
   Next: enquiry form (Leads) and per-client onboarding link inside the app (no passwords).
4. Small: status badges in dark mode; carousel slide overflows on phone (content page).
5. Later, only when asked: remove `vendor_old`,
   `vendor_prev_l12` and the old `workflow.megatechzy.com` folder; real logo file.
