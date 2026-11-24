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
- AI for drafts only (team checks and posts): "Write with AI" on Comments and Reviews replies; "Generate with AI" on a post (SMM) fills caption + hashtags from the calendar row via Groq
  (free plan, `GROQ_API_KEY`, model `GROQ_MODEL`), in one of the client's ticked "Caption languages" (picked next to the button) and following its "Caption style" (Clients > Edit, wins over the default
  8-12 full lines / 120-200 words, search friendly: hook with the searched words + city, 3-5 natural keywords, CTA, max 3 emojis,
  8-15 topic/niche/city hashtags). Owner's rule: at least 6 lines and 80 words; a shorter answer is asked once more.
  Default model `openai/gpt-oss-120b`. Nothing is saved until the SMM saves.
- Change after approval ("Need a change?" on an Approved / Scheduled post, not once it is live on FB / IG; cancels its FB / IG schedule):
  "Revision" (brand SMM, Admin or the brand's Customer) = full round again (Designer V2 → SMM → Customer);
  "Send back for changes" (SMM / Admin, small fix like a logo or number) = Designer V2 → the SMM's approval is final (`contents.minor_fix`).
  Designers / Editors replace files only before the SMM's review.
- Quick post (permission "Quick post", Admin gives it in Roles / Users > Edit; button on Calendars + Schedule): the SMM uploads a ready photo /
  carousel / reel + caption for a brand and date → Approved at once (no designer task, no Customer approval), added to that month's
  calendar (made Approved if none) as a row marked "Quick post", then published with the normal FB / IG buttons.
- No caption, no scheduling: the manual "Scheduled" step and Facebook / Instagram publish (now or scheduled) need a caption on the current version.
- One content format per calendar row; platforms can be several. A same-day Post and Reel are two rows.
- Draft rows may have no date (import or form); every row needs a date before the calendar is submitted.
- Roles: Admin, Operation Manager (`manager`), Social Media Manager, Graphic Designer, Video Editor, Customer (`client`).
- Task page: "Files for this post" shows the row's files (Customer / team uploads: picture or video player + Download) above the upload box;
  the Tasks list tags rows that have files. The Designer / Editor of a row can always open its files.
- Designers/Editors never see caption, hashtags or approver, and only see formats for their role.
  In Calendars and Schedule they see only the rows whose task is assigned to them.
- Draft calendars are visible only to their creator, Admin and Ops Manager.
- Delete calendar: a Draft by whoever may edit it; any other status by Admin only, never once a post of it is Published or live on FB / IG
  (its tasks, posts, uploaded files, FB / IG queue and unsent GBP posts go with it).
- Calendars list = month tabs for last / this / next month (+ the one after) when they have calendars (count + a dot when one waits for someone),
  other months in a "More months" dropdown, "All" = every month; opens on the coming month (else this month, else the newest).
- Inside a calendar: row filters Format, Customer check, Work stage and Assigned to (the last two only with task columns, not for the Customer;
  only choices that the calendar has); Sr. No. keeps the row's number in the full calendar.
- Admin > Users: team members get "Brands"; auto-assign and the default approver follow them. A Social
  Media Manager with brands sees only those brands (calendars, approvals, schedule, chat, assets, reports).
- Internal brand (Clients > Edit, e.g. Mega Techzy): no Customer step; Ops approval assigns tasks, SMM approval = Approved.
- Chat groups: All team, per brand Client group and Internal. Customers get no chat push 22:00–09:00.
  "Discuss in chat" adds a post card to a message; SMM/Ops/Admin can also pick a post inside chat.
- Notifications are required: every role (Admin too), phone and laptop, sees a full-screen
  gate (`layouts/_push-gate`) until push is allowed on that device.
- Design: clean light (+ dark mode), logo colours teal `#0A7A6C` and amber `#F5A800`, icons not emojis, phone first (PWA).
- No browser popups: ask with `data-confirm="…"` on a form or submit button, and tell with `appAlert('…')`
  (`resources/js/confirm-dialog.js`, a centered dialog; a question starting with Delete / Remove / Reject gets a red button). Never `confirm()` / `alert()`.

- Packages (Admin > Packages, from the PDF): each client has one. Price only for Admin, roles with
  "See package prices" (Ops Manager by default) and the client's own Customer. Calendar page shows
  posts vs package quota; Designers/Editors see no services list.
- Module 7 (Admin only): Attendance (team checks in on Home; Sundays off), Salary = monthly × paid
  days ÷ working days (half day ½, leave/no check-in 0), Billing (one bill per client per month at
  package price, due on the 5th, or one-off invoices), payments give receipts, Expenses + month profit.
  Invoice/receipt numbers continue the old Refrens series (A00324 / A00039); PDF made in the browser.
  Billing flow (`App\Support\Billing`): `billing:monthly` on the 1st at 09:00 makes each package client's bill "Not sent":
  package line + last month's Meta ads spend of its ad account (when linked) + "Previous balance" lines of older sent bills
  still unpaid (no GST on those). The Admin is told, edits it (Edit bill: ad spend, extras, amounts) and sends it (Send / Send all /
  Save & send); only then the Customer sees it and is told, and the old bills become "Moved" (no payments or reminders there).
  `billing:reminders` at 10:00: 2 days before the due date, on it, then every 3 days overdue, + an Admin summary.
- Assets per brand = 3 folders: Brand files (uploads; All / Photos / Videos tabs inside), Published videos, Published photos (the final version's files of Published posts,
  read from the posts, newest first, month filter, Download + Open post; reel covers left out). The Customer sees their own brand's.
- Assets grid: videos show a picture and load only on Play (`x-video-tile`, `resources/js/video-tile.js`); no ffmpeg on the host, so the first
  browser that shows a video without a picture takes a frame (one video at a time) and saves it (`thumb_path`); a reel's cover is used when it has one.
- Assets: "Rename" on each file (uploader, Admin or any team member of the brand; a Customer only their own files); the extension stays.
- Public forms (no login): `/enquiry` → Leads (Clients permission; "Won" makes the client);
  `/onboarding/{token}` per client → client brief (Clients or Calendars permission). Never ask for passwords.

- Facebook / Instagram (Admin > Facebook / IG): Admin connects Facebook once (Meta app 1652918339673007,
  Standard access = only people with a role on the app); each client is linked to a Page. Approved content
  gets "Publish to Facebook + Instagram"; `meta:publish` (every minute) posts it and marks it Published.
  An Instagram container in ERROR (e.g. Meta's "Media upload has failed" 2207076) is made again 5 min later, 3 containers in all
  Reels: tries 1-2 send the video bytes to Instagram (resumable upload, no link to fetch; try 2 without the cover),
  try 3 gives Instagram Facebook's processed copy as a link (helps phone videos such as HEVC .mov).
  A reel whose upload itself fails (e.g. "Request processing failed") also moves on to the next try, not Failed at once.
- `meta:sync` (every 10 min): comments on our posts → Comments inbox (SMM replies as the page; filters To reply / Replied /
  Ignored / All, client, platform; "Ignore" + Undo for spam; Reviews have Ignore + Undo too, which cancels a scheduled auto reply); lead-form
  leads → Meta leads (grouped by client; the Customer sees only their own). Needs META_CONFIG_ID with
  comment, lead and insights permissions (config "Workflow full + insights").
  Meta leads: remark history (quick buttons + text, optional next follow-up; first remark makes New → Contacted),
  "Repeat" tag (same last-10-digit phone for the same client), search, "Follow-up today" filter (incl. missed),
  CSV export of the filtered list; `leads:followups` at 09:00 notifies brand SMMs + the Customer.
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
  Healthcare clients (Clients > Edit): replies never use the reviewer's name. Auto replies stay template-only; "Write with AI"
  (Groq) only fills one review's reply box, and the team posts it. Reply languages (several ticked):
  reply in the review's own language among them, else the first ticked (`ReviewTemplates::languageFor`).
- GBP Posts (Social media > GBP Posts, no approval, one post at a time): update / offer / event, 1 image, `gbp:posts`
  (every minute) publishes due posts. Try 1 gives Google our signed image link; tries 2–3 upload the bytes as a
  location photo (host firewall blocks some crawlers; `GBP_POST_MEDIA=upload` always uploads). After 3 fails: Failed + alert.
  An approved single-image Post (Approved / Scheduled / Published) has "Post to Google Business" on its page: own Google
  caption (pre-filled without hashtags; "Generate with AI (Google)" writes a local-search one), the first image copied, CTA, now or later.
- Review QR (Social media > Review QR; the Customer sees their own): Google's `newReviewUri` of the location (saved by
  Refresh locations), printable QR + WhatsApp / copy link.
- Reports > Google: profile views (Search / Maps), calls, directions, website clicks from the Business Profile Performance
  API (must be enabled in the Cloud project; cached 3 hours, Google is 2–3 days late) + the month's reviews and GBP posts.

- WhatsApp (top-level "WhatsApp", permission "WhatsApp inbox": Ops Manager by default, others via Users > Edit): the Mega Techzy
  number in coexistence (WhatsApp Business app + Cloud API on one number; phone + up to 4 WhatsApp Web stay in use). System user
  token + WABA / phone IDs + webhook verify token only in the `.env` (no Embedded Signup). Webhook `/webhooks/whatsapp` (signed with
  META_APP_SECRET) takes messages, statuses, smb_message_echoes (sent from phone/Web, shown as "WhatsApp app / Web"), history and
  smb_app_state_sync (phone's contact names). Free text / PDF / photo only within 24 h of the customer's last message; otherwise
  (and for "New WhatsApp" to a number) an approved template, + PDF/photo when its header has one. "WhatsApp Web" button opens the
  chat in a scanned browser. One push per waiting chat to everyone with the permission. Settings > WhatsApp: "Connect webhook" (+ one-time history sync).

## Next up

Phase 2: monthly checklist for non-post services (GBP posts, reports, strategy calls).
