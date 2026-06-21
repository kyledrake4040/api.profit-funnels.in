<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Funnel\Attribution\Attribution;
use App\Funnel\Attribution\AttributionReport;
use App\Funnel\Attribution\EloquentAttributionStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['funnel.attribution_driver' => 'eloquent']);
    }

    public function test_gohighlevel_webhook_writes_a_row_to_the_database(): void
    {
        $this->postJson('/api/funnel/webhooks/gohighlevel', [
            'utm_source' => 'funnel',
            'contact_id' => 'lead-1',
            'post_id' => 'post_1',
        ])->assertOk()->assertJson(['attributed_to_funnel' => true]);

        $this->assertDatabaseHas('funnel_attribution', [
            'lead_id' => 'lead-1',
            'utm_source' => 'funnel',
            'post_id' => 'post_1',
            'revenue_cents' => null,
        ]);
    }

    public function test_quickbooks_webhook_updates_revenue_in_the_database(): void
    {
        $this->postJson('/api/funnel/webhooks/gohighlevel', ['utm_source' => 'funnel', 'contact_id' => 'lead-1'])->assertOk();

        $this->postJson('/api/funnel/webhooks/quickbooks', ['customer_id' => 'lead-1', 'amount' => 699.00])
            ->assertOk()->assertJson(['updated_rows' => 1]);

        $this->assertDatabaseHas('funnel_attribution', [
            'lead_id' => 'lead-1',
            'revenue_cents' => 69900,
        ]);
    }

    public function test_dashboard_reads_from_the_database(): void
    {
        $this->postJson('/api/funnel/webhooks/gohighlevel', ['utm_source' => 'funnel', 'contact_id' => 'a'])->assertOk();
        $this->postJson('/api/funnel/webhooks/gohighlevel', ['utm_source' => 'google', 'contact_id' => 'b'])->assertOk();
        $this->postJson('/api/funnel/webhooks/quickbooks', ['customer_id' => 'a', 'amount' => 699.00])->assertOk();

        $this->actingAs(new User())->get('/funnel/dashboard?days=30')
            ->assertOk()
            ->assertSee('$699.00')
            ->assertSee('50%');
    }

    public function test_store_record_revenue_and_recorded_since(): void
    {
        $store = new EloquentAttributionStore();
        $now = 1_700_000_000;

        $store->record($this->row('a', 'funnel', $now));
        $store->record($this->row('b', 'funnel', $now - 40 * 86400)); // outside a 7-day window

        self::assertCount(1, $store->recordedSince($now - 7 * 86400));

        $updated = $store->setRevenueForLead('a', 50000);
        self::assertSame(1, $updated);
        self::assertSame(50000, $store->allForLead('a')[0]->revenueCents);

        $summary = (new AttributionReport($store))->summarize(7, $now);
        self::assertSame(1, $summary['funnel']['leads']);
        self::assertSame(50000, $summary['funnel']['revenue_cents']);
    }

    private function row(string $leadId, string $source, int $createdAt): Attribution
    {
        return new Attribution(
            id: 'tmp',
            postId: 'p',
            platform: 'tiktok',
            utmSource: $source,
            utmMedium: '',
            utmCampaign: '',
            leadId: $leadId,
            revenueCents: null,
            createdAt: $createdAt,
        );
    }
}
