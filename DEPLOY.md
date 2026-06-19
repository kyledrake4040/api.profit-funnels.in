# Deploying the Backend (`api.profit-funnels.in`)

This Laravel 8 app exposes a Stripe webhook at `/api/stripe/webhook` that records
payments and syncs paid orders into HubSpot. It needs PHP 8.0+, Composer, and a
MySQL database.

## Environment variables

Copy `.env.example` to `.env` and set:

```
APP_KEY=                      # php artisan key:generate
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.profit-funnels.in

DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=whsec_...
HUBSPOT_TOKEN=
```

## Option A — cPanel / shared hosting

1. Pull the code from `main`; point the domain document root at the project root
   (the included `.htaccess` forwards requests to `public/`).
2. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Create the `.env` (above) and the MySQL database, then:
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache
   ```
4. Ensure HTTPS is enabled (Stripe requires `https`).

## Option B — Railway

1. New Project → Deploy from GitHub → this repo.
2. Add a MySQL plugin and map its credentials to the `DB_*` variables.
3. Add the environment variables above in the Variables tab.
4. Build / start:
   ```
   composer install --no-dev --optimize-autoloader && php artisan migrate --force
   ```
   Serve via a PHP web server buildpack (not `artisan serve`) for production.

## Connect Stripe

1. Stripe Dashboard → Developers → Webhooks → Add endpoint.
2. URL: `https://api.profit-funnels.in/api/stripe/webhook`
3. Events: `checkout.session.completed`, `invoice.paid`,
   `payment_intent.payment_failed`.
4. Copy the signing secret into `STRIPE_WEBHOOK_SECRET`, then
   `php artisan config:cache`.

## Verify

- Send a test `checkout.session.completed` event from Stripe.
- Check `storage/logs/laravel.log` for `Stripe payment event recorded` and
  `HubSpot sync complete`.
- Confirm a row in the `payments` table and a contact/deal in HubSpot.

## Notes

- Keep `APP_DEBUG=false` in production.
- `php artisan serve` is for local testing only — use a real web server for
  production traffic.
