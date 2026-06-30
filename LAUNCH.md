# Launch checklist — from code to first paid customer

This app is already built. The only thing between it and revenue is **configuration**:
getting it onto a public URL with a real database and live Stripe keys. This guide
walks the whole get-paid chain in order. At any point, run:

```bash
php artisan funnel:launch-check
```

That command inspects this exact chain and tells you, in plain English, what's still
missing. **When it prints `READY`, you can take money.** Work top to bottom; re-run it
after each step.

---

## The get-paid chain (what you're wiring up)

```
visitor lands on /  →  clicks a plan  →  Stripe charges them  →  webhook provisions
   (LandingController)   (CheckoutController)   (Stripe)            their account
                                                                  (StripeWebhookController)
```

Every step below exists to make one link in that chain real.

---

## 1. Get it onto a public server

Stripe has to be able to redirect a paying customer back to your site, and Stripe's
webhook has to be able to reach you — so `localhost` can't take live payments. You need
a host with a public HTTPS URL. Cheapest realistic options:

- **A managed platform** (least setup): Laravel Forge + a $6/mo DigitalOcean droplet, or
  Railway / Render. These handle PHP, HTTPS, and deploys for you.
- **A plain VPS** ($5–6/mo): DigitalOcean / Hetzner / Vultr with PHP 8.2+, Composer, a
  web server, and MySQL.

Point a domain (or use the host's free subdomain) at it and make sure HTTPS is on.

## 2. Configure the environment

Copy the example and fill it in:

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env`:

| Variable | What to set it to |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | your real **https://** URL |
| `DB_*` | your database host / name / user / password |
| `STRIPE_KEY` | Stripe **publishable** key |
| `STRIPE_SECRET` | Stripe **secret** key |
| `STRIPE_WEBHOOK_SECRET` | the signing secret from step 4 (do this after creating the webhook) |
| `MAIL_*` | an email sender (so trial/receipt emails send) |

> Start with Stripe **test mode** keys (`sk_test_…`). Switch to live keys only once a
> test purchase works end-to-end.

## 3. Create the database

```bash
php artisan migrate --force
php artisan db:seed --force   # creates roles + your initial login
```

## 4. Wire the Stripe webhook (this is the link people forget)

Without this, a customer can be **charged but never provisioned** — they pay and get
nothing. In the Stripe Dashboard → **Developers → Webhooks → Add endpoint**:

- **Endpoint URL:** `https://YOUR-DOMAIN/api/stripe/webhook`
- **Events:** at minimum `checkout.session.completed` (and `invoice.paid` for renewals).
- Copy the endpoint's **Signing secret** (`whsec_…`) into `STRIPE_WEBHOOK_SECRET` in `.env`.

## 5. Confirm you're ready

```bash
php artisan config:clear
php artisan funnel:launch-check
```

Keep going until it prints **`READY`**. Every red line names the exact fix.

## 6. Do one real test purchase

With test-mode keys, open your site, click a plan, and pay with Stripe's test card
`4242 4242 4242 4242` (any future expiry, any CVC). Confirm:

1. You're redirected to the success page.
2. A subscription row was created (check the Stripe dashboard **and** log into `/app`).

If provisioning didn't happen, it's almost always the webhook (step 4) — check
**Stripe → Webhooks → your endpoint** for failed deliveries.

## 7. Go live

Swap the test keys for **live** Stripe keys, repeat step 4 with a live webhook endpoint
(live and test webhooks are separate), re-run `funnel:launch-check`, and do one small
real purchase yourself to confirm money lands in your Stripe balance.

---

## Getting the first customer (the part that actually pays you)

The software being live doesn't create income — a customer does. Fastest paths, in order:

1. **Sell to people you already know.** Every business you've worked with that needs
   leads, quotes, invoices, or a simple site is a prospect. The product does all four.
2. **Use the built-in pieces as the pitch.** You can stand up a client micro-site
   (`/s/{slug}`) and send a pay-link invoice (`/pay/{token}`) in minutes — demo *those*,
   because they produce a visible result for the customer immediately.
3. **Lead with the trial.** Signups get an 8-day free trial (`FUNNEL_TRIAL_DAYS`), so the
   ask is "try it free," not "buy it."

You don't need traffic or ads to get the first few customers — you need a handful of
direct conversations. One paying customer on the `pro` plan is **$249/month** recurring.

---

*Health/income note: if the ladder fall happened on a job, a workers' compensation claim
is usually the fastest income while you recover, and it often must be reported to the
employer within days. In the US, dialing 211 (or 211.org) connects you to local
emergency rent/food/medical help. Those move faster than any software launch.*
