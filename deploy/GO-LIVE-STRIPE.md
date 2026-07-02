# Go Live: charging for Maritime Geo with Stripe

This is the checklist to turn the funnel from "plumbing built" into "money in the
bank." The code is already wired — a paid checkout provisions a `User` + active
`Subscription` automatically (see `SubscriptionProvisioner`). You just need to
connect a real Stripe account and flip on the keys.

Until `STRIPE_SECRET` is set, the pricing buttons **gracefully fall back to the
lead form** — no charges happen and nothing breaks. So you can deploy this safely
before going live.

## 1. Create / log in to Stripe

1. Sign up at <https://dashboard.stripe.com> with your business details
   (Gulf Coast Painting PEI / your operating entity).
2. **Connect your bank account** under **Settings → Business → Payouts**. This is
   where Stripe deposits your money. A Canadian Stripe account pays out in **CAD**.
3. Complete identity/business verification or Stripe will hold payouts.

## 2. Where the money goes

```
Customer's card → your Stripe balance → automatic payout → your bank account
```

- Your server never holds funds and never sees card numbers — Stripe does all of
  that on its own hosted checkout page.
- Default payout schedule is rolling (typically 2–7 business days for a new
  account, faster once established). Adjustable in **Settings → Payouts**.

## 3. Currency

The app bills in **USD** by default (`FUNNEL_SERVICE_CURRENCY=usd`,
`config/funnel.php`). The plan prices are **$94 / $294 / $494 USD** per month —
deliberately $3/mo under GoHighLevel's equivalent tiers ($97 / $297 / $497), which
are priced in USD, so we match the market we're competing in.

**Payout note for a Canadian Stripe account.** Charges settle in USD, then Stripe
converts USD→CAD on payout at ~mid-market + ~2% FX fee. That FX cost is the
tradeoff for pricing head-to-head against GoHighLevel in USD. If you'd rather
avoid the FX fee and bill Canadian customers in CAD, set
`FUNNEL_SERVICE_CURRENCY=cad` and update the prices in `config/funnel.php` and
`database/seeders/PlanSeeder.php` to your CAD numbers. (Charging multiple
presentment currencies natively is a later upgrade via Stripe Adaptive Pricing.)

## 4. Set the production environment variables

In your host (Railway / server `.env`):

```
STRIPE_SECRET=sk_live_xxx            # Developers → API keys → Secret key
STRIPE_KEY=pk_live_xxx               # (publishable key; optional here)
STRIPE_WEBHOOK_SECRET=whsec_xxx      # from step 5
FUNNEL_SERVICE_CURRENCY=usd          # or cad
APP_URL=https://app.maritimegeo.ca
```

Rehearse with **test keys** (`sk_test_…`) and Stripe's
[test cards](https://stripe.com/docs/testing) (e.g. `4242 4242 4242 4242`)
before switching to live keys.

## 5. Register the webhook (required for auto-provisioning)

Payments will succeed without this, but new customers won't get an account until
you wire the webhook so Stripe can tell us the checkout completed.

1. Stripe dashboard → **Developers → Webhooks → Add endpoint**.
2. Endpoint URL: `https://app.maritimegeo.ca/api/stripe/webhook`
3. Events to send — all four are handled by `StripeWebhookController`:
   - **`checkout.session.completed`** — provisions the account + active subscription.
   - **`customer.subscription.updated`** — renewals (extends `ends_at`) and
     `past_due` transitions.
   - **`customer.subscription.deleted`** — marks the subscription cancelled.
   - **`invoice.paid`** — optional; flips an online-paid invoice to Paid.
4. Copy the endpoint's **Signing secret** (`whsec_…`) into `STRIPE_WEBHOOK_SECRET`.

The webhook signature is verified in `StripeWebhookController`; a missing/invalid
secret is rejected, so this must match.

## 6. Smoke test the live flow

1. Open `/` → click a pricing button → you should land on Stripe's hosted page.
2. Pay with a real card (or a test card on test keys).
3. You're redirected to `/checkout-success`.
4. Confirm in the DB:
   - a row in `payments` for the `checkout.session.completed` event,
   - a `users` row for the buyer's email,
   - an **Active** `subscriptions` row linked to the right plan.
5. Confirm the payout appears in **Stripe → Balance**.

## 7. Now handled (shipped since first draft)

- **Onboarding email**: new accounts are emailed a one-time set-password link the
  moment their subscription clears (`WelcomeEmail`, queued from
  `SubscriptionProvisioner`). Requires a working mailer + a running queue worker
  (`php artisan queue:work`, or `QUEUE_CONNECTION=sync` for inline sends).
- **Renewals & churn**: `customer.subscription.updated` extends `ends_at` and
  flips `past_due`; `customer.subscription.deleted` marks the subscription
  cancelled. Register those two events (step 5) or long-term state will drift.

## 8. Still on the backlog (honest)

- **Refunds/disputes**: handled in the Stripe dashboard; not reflected in the app
  DB yet.
- **Failed-payment dunning**: `past_due` is recorded, but there's no automated
  reminder email sequence yet.
