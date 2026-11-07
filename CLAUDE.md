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
- Customer row check: rows can be ticked and approved together; a Reel whose client video is not ready can be
  "Video later" (calendar still gets approved, no task for it) and is approved on the approved calendar later, which makes its task.
  Approvals list: client filter + tick and approve together (SMM step or Customer step); revisions stay one by one.
- AI only for captions: "Generate with AI" on a post (SMM) fills caption + hashtags from the calendar row via Groq
  (free plan, `GROQ_API_KEY`, model `GROQ_MODEL`), in the client's "Caption language" and following its "Caption style" (Clients > Edit, wins over the default
  2-4 lines / CTA / max 2 emojis / 5-10 hashtags); nothing is saved until the SMM saves.
- No caption, no scheduling: the manual "Scheduled" step and Facebook / Instagram publish (now or scheduled) need a caption on the current version.
- One content format per calendar row; platforms can be several. A same-day Post and Reel are two rows.
- Draft rows may have no date (import or form); every row needs a date before the calendar is submitted.
- Roles: Admin, Operation Manager (`manager`), Social Media Manager, Graphic Designer, Video Editor, Customer (`client`).
- Designers/Editors never see caption, hashtags or approver, and only see formats for their role.
  In Calendars and Schedule they see only the rows whose task is assigned to them.
- Draft calendars are visible only to their creator, Admin and Ops Manager.
- Admin > Users: team members get "Brands"; auto-assign and the default approver follow them. A Social
  Media Manager with brands sees only those brands (calendars, approvals, schedule, chat, assets, reports).
- Internal brand (Clients > Edit, e.g. Mega Techzy): no Customer step; Ops approval assigns tasks, SMM approval = Approved.
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

- Facebook / Instagram (Admin > Facebook / IG): Admin connects Facebook once (Meta app 1652918339673007,
  Standard access = only people with a role on the app); each client is linked to a Page. Approved content
  gets "Publish to Facebook + Instagram"; `meta:publish` (every minute) posts it and marks it Published.
- `meta:sync` (every 10 min): comments on our posts → Comments inbox (SMM replies as the page); lead-form
  leads → Meta leads (grouped by client; the Customer sees only their own). Needs META_CONFIG_ID with
  comment, lead and insights permissions (config "Workflow full + insights").
- Reports (Monthly, Posting, Ads, Leads) per brand + month; numbers from Meta are cached 3 hours (`MetaInsights`).
- Sidebar is grouped (Content, Social media, Meta ads, Clients, Team, Accounts, Settings) in `Navigation::items`.
- Meta ads: Ads (optional saved audience; optional A/B test = 2 ad sets at half budget + Meta split test), Audiences
  (Page/IG engagers, website visitors, customer list hashed SHA-256, lookalike), Pixel (one per client, website code, custom conversions).

- Google Business (Admin > Google Business): Admin connects Google once (Cloud project megatechzy-workflow,
  scope business.manage, redirect `/google/callback`); each GBP location is linked to one client there.
- Reviews (Social media > Reviews, `gbp:reviews` every 15 min, `gbp:replies` every minute): 4–5 stars → template
  auto reply after 10–30 min (client's auto reply on), 3 stars → template draft to approve, 1–2 stars → no reply,
  Admin / Ops / brand SMMs alerted. First sync of a location only imports old reviews. Templates (Admin > Review
  templates) per client + a default set copied to new clients; the last 3 used on a location are not repeated.
  Healthcare clients (Clients > Edit): replies never use the reviewer's name. No AI.
- GBP Posts (Social media > GBP Posts, no approval, one post at a time): update / offer / event, 1 image, `gbp:posts`
  (every minute) publishes due posts. Try 1 gives Google our signed image link; tries 2–3 upload the bytes as a
  location photo (host firewall blocks some crawlers; `GBP_POST_MEDIA=upload` always uploads). After 3 fails: Failed + alert.

## Next up

Phase 2: monthly checklist for non-post services (GBP posts, reports, strategy calls).
