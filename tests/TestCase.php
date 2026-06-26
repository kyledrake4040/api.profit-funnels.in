<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // The `api` guard is backed by Passport (config/auth.php), so building
        // the guard — which Passport::actingAs() and any auth:api request do —
        // requires OAuth signing keys. Generate them once per run so a fresh
        // checkout or CI (neither of which ships keys) doesn't fail with
        // "Invalid key supplied".
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }
    }
}
