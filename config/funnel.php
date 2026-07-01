<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funnel webhook shared secret
    |--------------------------------------------------------------------------
    |
    | When set, inbound funnel webhooks (GoHighLevel, QuickBooks) must present
    | this secret as an "X-Funnel-Token" header or a "?token=" query parameter.
    | Leave empty to disable verification (e.g. local/dry-run).
    |
    */

    'webhook_secret' => env('FUNNEL_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Attribution store driver
    |--------------------------------------------------------------------------
    |
    | "eloquent" persists attribution to the funnel_attribution table
    | (concurrency-safe; recommended in production). "json" uses a flat file
    | (zero-infra; fine for local/dry-run and the CLI).
    |
    */

    'attribution_driver' => env('FUNNEL_ATTRIBUTION_DRIVER', 'json'),

    /*
    |--------------------------------------------------------------------------
    | JSON store path
    |--------------------------------------------------------------------------
    |
    | Where the "json" attribution driver persists rows. Defaults to
    | storage/funnel/attribution.json; overridable so tests can isolate it.
    |
    */

    'json_store_path' => env('FUNNEL_JSON_STORE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Engine master switch & queue path
    |--------------------------------------------------------------------------
    |
    | "enabled" mirrors FUNNEL_ENABLED: the engine stays dormant (publishes
    | nothing) until it is true. "queue_path" is where the content queue lives;
    | overridable so tests can isolate it.
    |
    */

    'enabled' => env('FUNNEL_ENABLED', false),

    'queue_path' => env('FUNNEL_QUEUE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Lead forward URL
    |--------------------------------------------------------------------------
    |
    | Optional. When set, leads captured by the public sales site are also
    | POSTed to this URL (e.g. a GoHighLevel inbound webhook) so they land in
    | your CRM in addition to the attribution store. Failures never block the
    | visitor's submission.
    |
    */

    'lead_forward_url' => env('FUNNEL_LEAD_FORWARD_URL'),

    /*
    |--------------------------------------------------------------------------
    | Service plans (Stripe checkout)
    |--------------------------------------------------------------------------
    |
    | Monthly price (in cents) for each ProfitProof plan the pricing buttons
    | sell. Checkout uses Stripe subscription mode; requires STRIPE_SECRET.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Free trial period
    |--------------------------------------------------------------------------
    |
    | Number of days from registration a user can access the platform without
    | an active subscription. Set to 0 to disable the trial entirely.
    |
    */

    'trial_days' => (int) env('FUNNEL_TRIAL_DAYS', 8),

    /*
    |--------------------------------------------------------------------------
    | Skip subscription check (dev / testing)
    |--------------------------------------------------------------------------
    */

    'skip_subscription_check' => env('FUNNEL_SKIP_SUBSCRIPTION_CHECK', false),

    'currency' => env('FUNNEL_SERVICE_CURRENCY', 'cad'),

    'plans' => [
        'starter' => 9900,
        'pro' => 24900,
        'done_for_you' => 49900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Launch promotion
    |--------------------------------------------------------------------------
    |
    | The limited-time offer shown on the landing page. The deadline drives the
    | urgency copy AND gates it: once the deadline passes the promo is hidden
    | automatically, so the page never advertises a discount that has expired.
    | Set FUNNEL_PROMO_ENABLED=false to hide it early, or move the deadline out.
    |
    */

    'promo' => [
        'enabled' => (bool) env('FUNNEL_PROMO_ENABLED', true),
        'label' => env('FUNNEL_PROMO_LABEL', '50% off your first 3 months'),
        'deadline' => env('FUNNEL_PROMO_DEADLINE', '2026-09-01'),
    ],

];
