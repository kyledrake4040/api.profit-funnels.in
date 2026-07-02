# GHL Build Sheet — click this, paste that, done

Goal: build the three core workflows in ONE clean sub-account, snapshot it,
get the share link. That link + the install guide (DIGITAL_PRODUCTS.md) = your
Gumroad product. Total hands-on time: ~30 minutes. Everything in a grey box is
ready to paste as-is — GHL fills the {{curly}} parts automatically per contact.

**Before you start (5 min):**
- Settings → change your password (it was exposed in chat) + turn on 2FA.
- Create the sub-account you'll build in: **Agency View → Sub-Accounts →
  + Add Sub-Account → blank**. Name it `SNAPSHOT MASTER — do not sell from here`.
  Build everything below inside THIS sub-account.

---

## Workflow 1 — Missed-Call Text-Back (10 min)

**Path:** Sub-account → **Automation → Workflows → + Create Workflow**.
Look for the **recipe library** and pick **"Missed Call Text Back"** if offered
(fastest). If you start from scratch instead:

1. **Trigger:** + Add New Trigger → **Call Status** → filter: **Call direction =
   Incoming**, **Call status = missed / no answer / busy**.
2. **Action 1:** + → **Send SMS** → paste:

   ```
   Hey, it's {{location.name}} — sorry we missed your call! We're probably up a
   ladder. What do you need done? Text back here and we'll get you sorted today.
   ```

3. **Action 2:** + → **Internal Notification → SMS/Email to assigned user** → paste:

   ```
   Missed call from {{contact.phone}} ({{contact.name}}). Auto text-back sent —
   watch for their reply.
   ```

4. Top-right: name it `Missed-Call Text-Back` → **Save → Publish**.

**Test:** call the sub-account's phone number from your cell, let it ring out.
You should get the text within ~10 seconds. (No number yet? Settings → Phone
Numbers → add one first.)

---

## Workflow 2 — Review Machine (10 min)

1. **Automation → Workflows → + Create Workflow** (recipe: **"Send Review
   Request"** if offered).
2. **Trigger:** **Opportunity Status Changed** → filter: **Status = Won**.
   (Meaning: when you mark a job "Won/Done", this fires.)
3. **Action 1:** **Send SMS** → paste:

   ```
   Hi {{contact.first_name}}, thanks for choosing {{location.name}}! If you're
   happy with the work, a quick Google review would mean the world to a local
   business like ours: {{location.google_review_link}} — takes 60 seconds. Thank you!
   ```

   (If the review-link merge field isn't set up: Settings → Reputation → add the
   Google review link, or just paste the business's actual review URL.)

4. **Action 2:** **Wait** → 3 days.
5. **Action 3:** **If/Else** → condition: contact replied / review received
   (or skip the condition and keep it simple) → **Send SMS**:

   ```
   Hi {{contact.first_name}} — no pressure at all, just a friendly nudge: if you
   have 60 seconds for a Google review it really helps us out.
   {{location.google_review_link}} Either way, thanks again for the business!
   ```

6. Name it `Review Machine` → **Save → Publish**.

---

## Workflow 3 — Speed-to-Lead (10 min)

1. **+ Create Workflow** (recipe: **"Fast Five"** / lead-nurture recipe if offered).
2. **Trigger:** **Form Submitted** (+ add a second trigger: **Facebook Lead Form
   Submitted**, if the account uses FB forms).
3. **Action 1:** **Send SMS** → paste:

   ```
   Hey {{contact.first_name}}, {{location.name}} here — got your request, thanks!
   Quick one so we can quote you fast: what's the job and roughly when do you
   want it done? Text back here.
   ```

4. **Action 2:** **Send Email** → subject `Got your request — {{location.name}}` → paste:

   ```
   Hi {{contact.first_name}},

   Thanks for reaching out to {{location.name}}. We've got your details and
   we'll be in touch shortly — usually within the hour during the day.

   Fastest way to a quote: reply with the job, the address area, and any photos.

   Talk soon,
   {{location.name}}
   ```

5. **Action 3:** **Create/Update Opportunity** → Pipeline: `Jobs` → Stage: `Lead`.
   (Create the pipeline first if asked: **Opportunities → Pipelines → + New**:
   stages `Lead → Quoted → Booked → Done → Reviewed`.)
6. **Action 4:** **Internal Notification** to owner: `New lead: {{contact.name}},
   {{contact.phone}} — auto-reply sent, opportunity created.`
7. Name it `Speed-to-Lead` → **Save → Publish**.

---

## (Flagship only) Workflow 4 — No-Show Killer (5 min)

1. **+ Create Workflow** → **Trigger: Appointment Booked** (recipe:
   "Appointment Confirmation + Reminder" if offered).
2. **Send SMS** immediately:

   ```
   {{contact.first_name}}, you're booked with {{location.name}} for
   {{appointment.start_time}}. Reply C to confirm or R to reschedule.
   ```

3. **Wait → until 24 hours before appointment** → **Send SMS**:

   ```
   Reminder: {{location.name}} tomorrow at {{appointment.start_time}}. See you then!
   ```

4. Name it `No-Show Killer` → **Save → Publish**.

## (Flagship only) Workflow 5 — Reactivation (5 min)

1. **+ Create Workflow** → **Trigger: Contact Tag Added** → tag = `reactivate`.
2. **Send SMS**:

   ```
   Hi {{contact.first_name}}, it's {{location.name}} — we did work for you a
   while back. We've got a couple of openings this month, so if you've been
   putting anything off, now's a good time. Want a quick quote? (Reply STOP to
   opt out.)
   ```

3. Name it `Reactivation — tag "reactivate"` → **Save → Publish**.
   (Buyers use it by bulk-tagging their old customers — that's in their guide.)

---

## Snapshot it → the sellable link (5 min)

1. Double-check: every workflow shows **Published**, the `Jobs` pipeline exists.
2. **Agency View → Account Snapshots → + Create Snapshot** → pick your
   `SNAPSHOT MASTER` sub-account → name it `Local Service CRM in a Box v1`.
3. On the snapshot row: **⋮ / Share → Permalink** (choose the permalink/link
   option) → **copy the link**. That link IS the $197 product.
4. For the two cheaper products: duplicate the master approach — create snapshot
   again but first disable/delete the workflows not in that tier, snapshot, name
   it (`Missed-Call Money Machine v1`, `Review Machine + Speed-to-Lead v1`),
   share link for each, then re-enable everything.

## List it (15 min, phone works)

1. gumroad.com → sign up → **New product → Digital product**.
2. Title/description/FAQ: copy from DIGITAL_PRODUCTS.md.
3. Attach: the install-guide PDF + a one-page PDF containing the snapshot link.
4. Price ($37 / $67 / $197) → **Publish**.
5. Buy the cheapest one yourself once to see exactly what a buyer receives.
6. Post the soft pitch in one GHL Facebook group (copy in DIGITAL_PRODUCTS.md).

---

**Merge-field note:** exact field names can differ slightly by account (the
picker in the SMS editor shows what's available — tap the tag icon and choose
Contact → First Name etc. rather than typing curly braces by hand if unsure).

**Order of operations if energy is short:** Workflow 1 → snapshot just that →
list the $37 product TODAY. Small win first; the flagship can be tomorrow.
