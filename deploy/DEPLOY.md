# Deploy the Maritime Geo web app

This is the runbook to put the **web application** live at
`https://app.maritimegeo.ca` — the landing page, CRM console, API, and Stripe
checkout/webhook. Follow it top to bottom once; redeploys are a single `git push`.

> **Scope note.** The other files in `deploy/` (`Dockerfile`, `railway.json`,
> `funnel.crontab`, `README-FUNNEL.md`) deploy **only** the background funnel
> engine on a schedule — they do *not* serve the web app. This runbook is the
> web app. The two can run side by side or in the same server; they're
> independent.

Architecture reminder:
- `maritimegeo.ca` → **GoHighLevel** (marketing funnel — set up separately in GHL).
- `app.maritimegeo.ca` → **this Laravel app** (what you're deploying here).

---

## 0. What you need before starting

| Thing | Why | Where |
| --- | --- | --- |
| A server host account | Runs PHP 8.2+, MySQL, nginx | [Laravel Forge](https://forge.laravel.com) + a $6 droplet (DigitalOcean/Hetzner/Vultr) |
| GoDaddy DNS access | Point `app.maritimegeo.ca` at the server | You already own the domain |
| Stripe account (verified) | Charge for plans | dashboard.stripe.com — see `deploy/GO-LIVE-STRIPE.md` |
| A transactional mail provider | Send the welcome/set-password email | Postmark, Resend, or Amazon SES |
| Anthropic API key (optional) | AI lead-reply drafts | console.anthropic.com — app works without it |

**Stack the app needs:** PHP **8.2+** (8.4 tested), **MySQL 8**, Composer,
a queue worker, and Laravel's scheduler. Forge provisions all of this for you.

---

## Path A — Laravel Forge (recommended)

Forge is a managed control panel: it installs nginx + PHP + MySQL + Redis,
manages deploys from GitHub, runs your queue worker and scheduler as daemons,
and issues free SSL. ~30 minutes end to end.

### A1. Provision the server

1. In Forge, connect your server provider (DigitalOcean etc.) and **Create Server**.
2. Choose PHP **8.4**, MySQL as the database. Pick the cheapest droplet ($6–12/mo
   is plenty to start).
3. Forge builds the box in a few minutes and shows you the **server IP**.

### A2. Create the site

1. **Sites → New Site.** Root domain: `app.maritimegeo.ca`.
2. Project type: **General PHP / Laravel**. Web directory: `/public` (default).
3. **Git Repository:** `kyledrake4040/api.profit-funnels.in`, branch `main`
   (deploy the merged, reviewed code — not the working branch).

### A3. Create the database

In Forge → **Database**, create a database (e.g. `maritimegeo`) and a user with a
strong password. Note the name / user / password for the env block below.

### A4. Environment variables

Forge → your site → **Environment**. Paste and fill in:

```dotenv
APP_NAME="Maritime Geo"
APP_ENV=production
APP_KEY=                              # generate in A5, or `php artisan key:generate`
APP_DEBUG=false
APP_URL=https://app.maritimegeo.ca

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maritimegeo
DB_USERNAME=maritimegeo
DB_PASSWORD=your-db-password

# Queue: database-backed so the welcome email is sent by a worker off the
# webhook thread (see A7). Set to "sync" only if you skip the worker.
QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=file

# Mail — REQUIRED for the welcome / set-password email. Example: Postmark.
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=your-postmark-token
MAIL_PASSWORD=your-postmark-token
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@maritimegeo.ca
MAIL_FROM_NAME="Maritime Geo"

# Stripe — see deploy/GO-LIVE-STRIPE.md. Without STRIPE_SECRET the pricing
# buttons safely fall back to the lead form (no charges), so you can deploy
# before these are live.
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx       # from A9
FUNNEL_SERVICE_CURRENCY=usd

# Optional AI drafts. Leave blank to keep AI features off.
CLAUDE_API_KEY=
CLAUDE_MODEL=

# Attribution store — DB-backed in production.
FUNNEL_ATTRIBUTION_DRIVER=eloquent

# Leave the background funnel engine off unless you're running it here too.
FUNNEL_ENABLED=false

# Browser origins allowed to call the API. The console is same-origin, so this
# can stay empty (falls back to APP_URL). Add GHL/other origins if they call in.
CORS_ALLOWED_ORIGINS=
```

### A5. Deploy script

Forge → your site → **Deploy Script.** Use this (it installs deps, migrates,
seeds the plans, and rebuilds caches on every deploy):

```bash
cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# App key on first deploy only (no-op if already set in the env).
[ -z "$(php artisan key:generate --show 2>/dev/null | grep base64)" ] || true
php artisan key:generate --force --no-interaction || true

php artisan migrate --force

# Seed the service plans. REQUIRED: checkout maps plan slugs (starter/pro/
# done-for-you) to these rows — without them, paid checkouts can't provision.
php artisan db:seed --class=Database\\Seeders\\PlanSeeder --force

# Rebuild caches.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reload PHP and restart the queue worker so new code is picked up.
( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
php artisan queue:restart
```

Then click **Deploy Now**.

### A6. One-time: Passport keys

This API uses **Laravel Passport** for auth, which needs encryption keys. Run
**once** from Forge → your site → **Commands** (or SSH):

```bash
php artisan passport:keys --force
php artisan passport:client --personal --no-interaction
```

> The keys live in `storage/`. On a Forge VPS the disk persists, so this is a
> one-time step. On an ephemeral/container host (Railway, Fly), instead generate
> them once locally and set `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` in the
> environment so they survive redeploys.

### A7. Queue worker

Forge → your site → **Queue.** New worker:
- Connection: `database`, Queue: `default`.
- Start it. This is what sends the welcome email off the webhook thread.

(If you'd rather not run a worker, set `QUEUE_CONNECTION=sync` in A4 — the email
then sends inline during the Stripe webhook. Simpler, slightly slower webhook.)

### A8. SSL + DNS

1. **DNS (GoDaddy):** Domain → DNS → add an **A record**:
   - Type `A`, Host `app`, Value `<your server IP>`, TTL default.
   - (Marketing `maritimegeo.ca` stays pointed at GoHighLevel — don't touch it.)
2. Wait for DNS to resolve (minutes to an hour). Check with
   `dig app.maritimegeo.ca` — it should return your server IP.
3. Forge → your site → **SSL → Let's Encrypt → Obtain Certificate.** Forge
   auto-renews it.

### A9. Register the Stripe webhook

Follow **`deploy/GO-LIVE-STRIPE.md` §5** — point the endpoint at
`https://app.maritimegeo.ca/api/stripe/webhook` and subscribe to all four events
(`checkout.session.completed`, `customer.subscription.updated`,
`customer.subscription.deleted`, `invoice.paid`). Copy the signing secret into
`STRIPE_WEBHOOK_SECRET` and redeploy.

### A10. Smoke test

1. Open `https://app.maritimegeo.ca/` → the landing page renders.
2. Open `https://app.maritimegeo.ca/app` → the CRM console login loads.
3. With Stripe **test** keys, click a pricing button → Stripe hosted checkout →
   pay with `4242 4242 4242 4242` → you land on `/checkout-success`.
4. Confirm in the DB: a `payments` row, a `users` row for the buyer, and an
   **Active** `subscriptions` row on the right plan.
5. Confirm the buyer received the welcome email (check the mail provider's log).
6. Swap to **live** keys when you're satisfied.

---

## Path B — Railway / Docker (alternative)

Railway works, but note: the `deploy/Dockerfile` in this repo builds the
**funnel engine only**, not the web app. To deploy the web app on Railway you'd
add a *separate* web Dockerfile (nginx + php-fpm serving `/public`), provision a
MySQL plugin, set the same env vars as A4, and set `PASSPORT_*_KEY` env vars
(A6 note) since the container disk is ephemeral. Forge is the lower-effort path
for a full Laravel app with Passport + queue + scheduler; use Railway only if you
specifically want containerized/ephemeral hosting. Ask and I can write the web
Dockerfile.

---

## Gotchas (the things that actually bite)

- **Seed the plans.** If `PlanSeeder` hasn't run, paid checkouts complete at
  Stripe but the webhook can't map the plan slug and skips provisioning. The A5
  script handles it; don't remove that line.
- **Passport keys must persist.** Regenerating them on every deploy invalidates
  all live login tokens. Generate once (A6) — or pin them via env on ephemeral hosts.
- **Mail must be real.** The mailtrap defaults in `.env.example` are dev-only.
  New paying customers can't log in until the welcome/set-password email actually
  sends, so verify your sending domain (SPF/DKIM) with the mail provider.
- **Queue worker or `sync`.** The welcome email is a queued job. Either run the
  worker (A7) or set `QUEUE_CONNECTION=sync`. With `database` queue and no worker,
  the email silently never sends.
- **`APP_DEBUG=false` in production.** Never expose stack traces publicly.
- **Deploy `main`, not the working branch.** Merge the reviewed PR first.
