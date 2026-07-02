<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The launch-check command is the deploy gate for "can we take money yet?".
 * These tests pin its contract: it fails loudly while a revenue blocker
 * remains, and only passes once the get-paid chain is fully wired.
 */
class LaunchCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_and_flags_stripe_when_keys_are_missing(): void
    {
        config([
            'services.stripe.secret' => '',
            'services.stripe.webhook_secret' => '',
        ]);

        $this->artisan('funnel:launch-check')
            ->assertExitCode(1)
            ->expectsOutputToContain('STRIPE_SECRET missing')
            ->expectsOutputToContain('NOT READY');
    }

    public function test_it_warns_that_a_paid_customer_could_go_unprovisioned_without_the_webhook_secret(): void
    {
        // The dangerous middle state: charging works, provisioning does not.
        config([
            'services.stripe.secret' => 'sk_test_123',
            'services.stripe.webhook_secret' => '',
        ]);

        $this->artisan('funnel:launch-check')
            ->assertExitCode(1)
            ->expectsOutputToContain('CHARGED but never provisioned');
    }

    public function test_it_reports_ready_once_the_get_paid_chain_is_wired(): void
    {
        config([
            'app.url' => 'https://app.example.com',
            'services.stripe.secret' => 'sk_test_123',
            'services.stripe.webhook_secret' => 'whsec_123',
            // Keep the DB check on the migrated in-memory schema simple.
            'funnel.attribution_driver' => 'json',
        ]);

        // Seed a user so the "owner login" check is satisfied (a warning,
        // not a blocker — but we assert the full green path here).
        \App\Models\User::factory()->create();

        $this->artisan('funnel:launch-check')
            ->assertExitCode(0)
            ->expectsOutputToContain('READY');
    }
}
