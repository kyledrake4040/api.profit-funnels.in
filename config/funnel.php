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

];
