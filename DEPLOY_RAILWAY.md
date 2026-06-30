# Deploy to Railway — step by step

Railway is the lowest-effort way to get this live: it deploys straight from your
GitHub repo, gives you HTTPS and a database, and needs no server admin. This guide is
specific to **this** app — the variable names and the webhook URL below are the real
ones. Total time: ~20–30 minutes, most of it waiting on builds.

The serve path is pure PHP (no front-end build step), and PHP 8.2 is already pinned in
`composer.json`, so Railway detects and builds it automatically.

> Throughout, run `php artisan funnel:launch-check` (Step 7) to see exactly what's still
> missing. When it says **READY**, you can take money.

---

## Before you start

- A **Railway** account → https://railway.app (sign in with GitHub).
- A **Stripe** account → https://stripe.com (start in **Test mode**).
- This repo on GitHub (you already have it).

---

## Step 1 — Create the project from your repo

1. Railway → **New Project** → **Deploy from GitHub repo** → pick
   `kyledrake4040/api.profit-funnels.in`.
2. When asked for the branch, choose the one you want to ship (e.g. `main` after PR #51
   is merged).
3. The first build will start and probably **fail or boot unhealthy** — that's expected,
   because the database and variables aren't set yet. Continue to Step 2.

## Step 2 — Add a MySQL database

1. In the project, click **New** → **Database** → **Add MySQL**.
2. Railway provisions it and exposes variables (`MYSQLHOST`, `MYSQLDATABASE`,
   `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLPORT`) you'll reference next.

## Step 3 — Set environment variables

Open your **app service** (not the database) → **Variables** → add these. The `${{ ... }}`
values are Railway *references* — type them exactly; Railway fills in the database values.

```
APP_NAME=ProfitProof
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:Djvc+V7/2+8rsY2wb/ANIXSTlxC2QpPwGoJDdHvaHig=

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

FUNNEL_ATTRIBUTION_DRIVER=eloquent
FUNNEL_TRIAL_DAYS=8
FUNNEL_SERVICE_CURRENCY=cad
```

> The `APP_KEY` above was generated for you. It's fine to use as-is, or replace it with
> your own. Don't reuse it across different apps.

You'll add `APP_URL` in Step 5 and the Stripe keys in Step 6.

## Step 4 — Run migrations on every deploy

App service → **Settings** → **Deploy** → **Pre-Deploy Command**:

```
php artisan migrate --force
```

This runs your database migrations before each new version goes live. (Seeding your
first login happens once, in Step 7.)

## Step 5 — Give it a public URL

1. App service → **Settings** → **Networking** → **Generate Domain**. You'll get
   something like `profitproof-production.up.railway.app`.
2. Back in **Variables**, add:
   ```
   APP_URL=https://YOUR-RAILWAY-DOMAIN
   ```
   (use the full `https://` domain from the previous step). Stripe redirects customers
   back to this URL, so it must be correct.

## Step 6 — Connect Stripe (test mode first)

1. Stripe Dashboard (Test mode) → **Developers → API keys**. Add to Railway Variables:
   ```
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   ```
2. Stripe → **Developers → Webhooks → Add endpoint**:
   - **Endpoint URL:** `https://YOUR-RAILWAY-DOMAIN/api/stripe/webhook`
   - **Events:** `checkout.session.completed` (add `invoice.paid` for renewals).
   - Create it, then copy the **Signing secret** (`whsec_...`) into Railway:
     ```
     STRIPE_WEBHOOK_SECRET=whsec_...
     ```

> This webhook is what turns a payment into a provisioned account. Without it a customer
> can be **charged but get nothing** — `funnel:launch-check` will block on this until it's set.

## Step 7 — Seed your login and verify readiness

Use the **Railway CLI** (`npm i -g @railway/cli`, then `railway login` and
`railway link` to this project), or the service's shell, to run one-offs:

```bash
railway run php artisan db:seed --force        # creates roles + your initial users
railway run php artisan funnel:launch-check     # should now print READY
```

`db:seed` is safe to run more than once (it skips rows that already exist). Fix any red
line `launch-check` reports, then re-run until it says **READY**.

The seeded super-admin login is in `database/seeders/UserSeeder.php`
(`super@profit-funnels.in`, password `password`) — **change that password immediately**
after your first login at `/app`.

## Step 8 — Do one test purchase

1. Open `https://YOUR-RAILWAY-DOMAIN` and click a plan.
2. Pay with Stripe's test card: **`4242 4242 4242 4242`**, any future expiry, any CVC.
3. Confirm you land on the success page, and that the subscription shows up in the Stripe
   dashboard **and** when you log into `/app`.

If the charge worked but nothing provisioned, check **Stripe → Webhooks → your endpoint**
for failed deliveries — that's almost always a wrong URL or a missing
`STRIPE_WEBHOOK_SECRET`.

## Step 9 — Go live

1. Swap the test keys for **live** Stripe keys (`pk_live_…`, `sk_live_…`) in Railway.
2. Create a **new** webhook endpoint in Stripe **Live mode** (live and test webhooks are
   separate) pointing at the same `/api/stripe/webhook` URL, and update
   `STRIPE_WEBHOOK_SECRET` with the live signing secret.
3. (Optional) Add your own domain: Railway **Settings → Networking → Custom Domain**, then
   update `APP_URL`.
4. Run `railway run php artisan funnel:launch-check` once more, then make one small **real**
   purchase yourself to confirm money lands in your Stripe balance.

You're live.

---

## Quick troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| Build succeeds, page 500s | `APP_KEY` not set, or DB vars wrong → check Step 3. |
| "no such table" / migration errors | Pre-deploy command missing → Step 4; or DB not linked → Step 2. |
| Pricing button just shows the lead form | `STRIPE_SECRET` not set → Step 6. |
| Customer charged but no account | `STRIPE_WEBHOOK_SECRET` missing/wrong, or webhook URL wrong → Step 6. |
| Stripe redirect fails | `APP_URL` wrong or not `https://` → Step 5. |

When in doubt: `railway run php artisan funnel:launch-check`.
