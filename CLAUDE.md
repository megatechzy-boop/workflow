# CLAUDE.md

Laravel 12 app: social media team + client workflow for MegaTechzy, live at
https://workflow.megatechzy.com. Setup, deploy steps and open items: `docs/DEV-NOTES.md`.

## How the owner wants to work

- Replies in short, simple Hinglish. App text and code stay in English.
- Commit and deploy only when the owner says so (they usually batch it: "commit, deploy baad me ek sath").
- Never put secrets (API keys, VAPID private key, passwords) in chat or in git; the owner pastes them into the server `.env`.
- Cron: one cPanel job runs `php artisan schedule:run` every minute (added 2026-10-30); the 9 am task
  digest (`tasks:remind`) is scheduled in `routes/console.php`. Ask before adding other cron jobs.
- Before building, push back on parts that are not needed; keep changes small and tested (`php artisan test`).

## Product rules (decided with the owner)

- Flow: SMM creates calendar → Ops Manager approves whole calendar → Customer approves each row
  (Approve, or Revision with a comment) → tasks auto-assigned (Reel → Video Editor, Post/Carousel →
  Graphic Designer, least-loaded; deadline = post date − 2 days) → Designer/Editor uploads files only →
  SMM approves (caption, hashtags, approver are SMM's) → Customer approves → Scheduled → Published → points.
- One content format per calendar row; platforms can be several. A same-day Post and Reel are two rows.
- Draft rows may have no date (import or form); every row needs a date before the calendar is submitted.
- Roles: Admin, Operation Manager (`manager`), Social Media Manager, Graphic Designer, Video Editor, Customer (`client`).
- Designers/Editors never see caption, hashtags or approver, and only see formats for their role.
- Chat groups: All team, per brand Client group and Internal. Customers get no chat push 22:00–09:00.
  "Discuss in chat" adds a post card to a message; SMM/Ops/Admin can also pick a post inside chat.
- Notifications are required: every role (Admin too), phone and laptop, sees a full-screen
  gate (`layouts/_push-gate`) until push is allowed on that device.
- Design: clean light (+ dark mode), logo colours teal `#0A7A6C` and amber `#F5A800`, icons not emojis, phone first (PWA).

- Packages (Admin > Packages, from the PDF): each client has one. Price only for Admin, roles with
  "See package prices" (Ops Manager by default) and the client's own Customer. Calendar page shows
  posts vs package quota; Designers/Editors see no services list.
- Module 7 (Admin only): Attendance (team checks in on Home; Sundays off), Salary = monthly × paid
  days ÷ working days (half day ½, leave/no check-in 0), Billing (one bill per client per month at
  package price, due on the 5th, or one-off invoices), payments give receipts, Expenses + month profit.
  Invoice/receipt numbers continue the old Refrens series (A00324 / A00039); PDF made in the browser.
- Public forms (no login): `/enquiry` → Leads (Clients permission; "Won" makes the client);
  `/onboarding/{token}` per client → client brief (Clients or Calendars permission). Never ask for passwords.

## Next up

Phase 2: monthly checklist for non-post services (GBP posts, reports, strategy calls).
