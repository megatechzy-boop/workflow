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
(`/home2/megatechzy/workflow/.env`) into the local `.env`. Never commit them.

## Deploy (cPanel, no SSH)

- App folder on the server: `/home2/megatechzy/workflow`
  (`/home2/megatechzy/workflow.megatechzy.com` is an old, unused install).
- Build assets locally (`npm run build`) and commit `public/build` (no Node on the server).
- Push `main`, then cPanel > Git Version Control > Update from Remote > Deploy HEAD Commit.
  `.cpanel.yml` copies the files, runs `migrate --force` and `optimize:clear`.
- Only when `composer.lock` changes: `composer install --no-dev` into a separate
  checkout of the `deploy-vendor` branch, push it, update `~/repositories/workflow-vendor`
  in cPanel, copy its `vendor` to `~/workflow/vendor_new`, then rename
  `vendor` -> `vendor_old` and `vendor_new` -> `vendor`. Then deploy `main`.
- The host sometimes shows "One moment, please…": reload and wait.

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
5. Later, only when asked: cron for `tasks:remind`; remove `vendor_old`,
   `vendor_prev_l12` and the old `workflow.megatechzy.com` folder; real logo file.
