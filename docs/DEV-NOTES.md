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

1. Force notifications: app not usable until "Turn on notifications" is done.
   To decide: which roles, laptops too, Admin exempt?
2. Resend: domain verifying; then put the API key in server `.env` as `MAIL_PASSWORD`
   and test with Admin > Users > Send test email.
3. Module 7: client billing, salary, attendance, expenses.
4. Small: status badges in dark mode; carousel slide overflows on phone (content page).
5. Later, only when asked: cron for `tasks:remind`; remove `vendor_old`,
   `vendor_prev_l12` and the old `workflow.megatechzy.com` folder; real logo file.
