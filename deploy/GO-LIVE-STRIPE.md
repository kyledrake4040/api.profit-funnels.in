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

The app bills in **CAD** by default (`FUNNEL_SERVICE_CURRENCY=cad`,
`config/funnel.php`). The plan prices are $99 / $249 / $499 CAD per month.

**Can American customers still pay?** Yes. They check out in CAD; their US bank
converts CAD→USD on their statement automatically. Because your Stripe account
settles in CAD, you receive CAD natively with **no Stripe FX fee**.

If you'd rather *charge* in USD, set `FUNNEL_SERVICE_CURRENCY=usd`. Then a Canadian
Stripe account receiving USD charges gets converted USD→CAD by Stripe at their
rate (~mid-market + ~2% FX fee) on payout. For a CAD-based business selling to a
mostly-Canadian audience, leaving it on `cad` is simplest and cheapest. (Charging
multiple presentment currencies natively is a later upgrade via Stripe Adaptive
Pricing.)

## 4. Set the production environment variables

In your host (Railway / server `.env`):

```
STRIPE_SECRET=sk_live_xxx            # Developers → API keys → Secret key
STRIPE_KEY=pk_live_xxx               # (publishable key; optional here)
STRIPE_WEBHOOK_SECRET=whsec_xxx      # from step 5
FUNNEL_SERVICE_CURRENCY=cad          # or usd
APP_URL=https://api.profit-funnels.in
```

Rehearse with **test keys** (`sk_test_…`) and Stripe's
[test cards](https://stripe.com/docs/testing) (e.g. `4242 4242 4242 4242`)
before switching to live keys.

## 5. Register the webhook (required for auto-provisioning)

Payments will succeed without this, but new customers won't get an account until
you wire the webhook so Stripe can tell us the checkout completed.

1. Stripe dashboard → **Developers → Webhooks → Add endpoint**.
2. Endpoint URL: `https://api.profit-funnels.in/api/stripe/webhook`
3. Events to send: at minimum **`checkout.session.completed`**.
   (Add `invoice.paid` and `customer.subscription.deleted` later for renewals
   and churn — not handled yet.)
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

## 7. Not done yet (honest backlog)

- **Onboarding email**: new accounts get a random password; they can't log in
  until you send a set-password / verify link. (Next PR candidate.)
- **Renewals & churn**: `invoice.paid` (extend `ends_at`) and
  `customer.subscription.deleted` (mark cancelled) aren't handled, so long-term
  subscription state can drift. (Next PR candidate.)
- **Refunds/disputes**: handled in the Stripe dashboard; not reflected in the app
  DB yet.
