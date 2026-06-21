<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Allowed origins are restricted to an explicit allow-list (no wildcard).
| Set CORS_ALLOWED_ORIGINS to a comma-separated list of front-end origins;
| it falls back to APP_URL. Note: the webhook endpoints are server-to-server
| and unaffected by CORS — this only governs browser callers (the SPA/login).
|
*/

$origins = (string) env('CORS_ALLOWED_ORIGINS', '');

$allowedOrigins = $origins !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $origins))))
    : [(string) env('APP_URL', 'http://localhost')];

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-Funnel-Token'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
