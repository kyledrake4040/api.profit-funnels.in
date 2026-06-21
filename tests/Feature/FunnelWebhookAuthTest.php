<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class FunnelWebhookAuthTest extends TestCase
{
    private string $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = base_path('storage/funnel/attribution.json');
        @unlink($this->store);
    }

    protected function tearDown(): void
    {
        @unlink($this->store);
        parent::tearDown();
    }

    public function test_without_a_configured_secret_the_webhook_is_open(): void
    {
        config(['funnel.webhook_secret' => '']);

        $this->postJson('/api/funnel/webhooks/gohighlevel', ['utm_source' => 'funnel', 'contact_id' => 'a'])
            ->assertOk();
    }

    public function test_configured_secret_rejects_missing_token(): void
    {
        config(['funnel.webhook_secret' => 'sh-secret']);

        $this->postJson('/api/funnel/webhooks/gohighlevel', ['utm_source' => 'funnel', 'contact_id' => 'a'])
            ->assertStatus(401);
    }

    public function test_configured_secret_rejects_wrong_token(): void
    {
        config(['funnel.webhook_secret' => 'sh-secret']);

        $this->withHeader('X-Funnel-Token', 'nope')
            ->postJson('/api/funnel/webhooks/quickbooks', ['customer_id' => 'a', 'amount' => 1])
            ->assertStatus(401);
    }

    public function test_correct_token_via_header_is_accepted(): void
    {
        config(['funnel.webhook_secret' => 'sh-secret']);

        $this->withHeader('X-Funnel-Token', 'sh-secret')
            ->postJson('/api/funnel/webhooks/gohighlevel', ['utm_source' => 'funnel', 'contact_id' => 'a'])
            ->assertOk()
            ->assertJson(['recorded' => true]);
    }

    public function test_correct_token_via_query_param_is_accepted(): void
    {
        config(['funnel.webhook_secret' => 'sh-secret']);

        $this->postJson('/api/funnel/webhooks/gohighlevel?token=sh-secret', ['utm_source' => 'funnel', 'contact_id' => 'a'])
            ->assertOk();
    }
}
