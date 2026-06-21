<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Exercises the funnel attribution webhook endpoints through the full HTTP
 * stack (routing + controllers), proving the GoHighLevel and QuickBooks
 * integrations work end-to-end — not just the framework-free core.
 */
class FunnelWebhookTest extends TestCase
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

    public function test_gohighlevel_webhook_records_a_funnel_lead(): void
    {
        $response = $this->postJson('/api/funnel/webhooks/gohighlevel', [
            'utm_source' => 'funnel',
            'utm_medium' => 'tiktok',
            'utm_campaign' => 'soft-wash',
            'post_id' => 'post_001',
            'platform' => 'tiktok',
            'contact_id' => 'lead-123',
        ]);

        $response->assertOk()
            ->assertJson([
                'recorded' => true,
                'attributed_to_funnel' => true,
            ]);
    }

    public function test_quickbooks_webhook_updates_revenue_for_a_known_lead(): void
    {
        $this->postJson('/api/funnel/webhooks/gohighlevel', [
            'utm_source' => 'funnel',
            'contact_id' => 'lead-123',
        ])->assertOk();

        $response = $this->postJson('/api/funnel/webhooks/quickbooks', [
            'customer_id' => 'lead-123',
            'amount' => 699.00,
        ]);

        $response->assertOk()->assertJson(['updated_rows' => 1]);
    }

    public function test_non_funnel_lead_is_not_attributed_to_funnel(): void
    {
        $response = $this->postJson('/api/funnel/webhooks/gohighlevel', [
            'utm_source' => 'google',
            'contact_id' => 'lead-456',
        ]);

        $response->assertOk()->assertJson(['attributed_to_funnel' => false]);
    }
}
