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

];
