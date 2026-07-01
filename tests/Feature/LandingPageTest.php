<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Funnel\Attribution\AttributionStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    private string $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = sys_get_temp_dir() . '/landing_' . uniqid() . '/attribution.json';
        config([
            'funnel.json_store_path' => $this->store,
            'funnel.attribution_driver' => 'json',
            'funnel.lead_forward_url' => null,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->store);
        @rmdir(\dirname($this->store));
        parent::tearDown();
    }

    public function test_landing_page_renders_the_pitch_and_pricing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('which marketing')
            ->assertSee('ProfitProof')
            ->assertSee('Get Pro');
    }

    public function test_live_promo_deadline_is_shown_with_its_date(): void
    {
        config([
            'funnel.promo.enabled' => true,
            'funnel.promo.label' => '50% off your first 3 months',
            'funnel.promo.deadline' => '2099-09-01',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('50% off your first 3 months')
            ->assertSee('Sep 1');
    }

    public function test_expired_promo_is_not_advertised(): void
    {
        config([
            'funnel.promo.enabled' => true,
            'funnel.promo.label' => '50% off your first 3 months',
            'funnel.promo.deadline' => '2000-01-01',
        ]);

        // The page still works; it just no longer makes an expired-discount claim.
        $this->get('/')
            ->assertOk()
            ->assertSee('ProfitProof')
            ->assertDontSee('50% off your first 3 months');
    }

    public function test_promo_can_be_disabled_outright(): void
    {
        config([
            'funnel.promo.enabled' => false,
            'funnel.promo.deadline' => '2099-09-01',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('New signups before');
    }

    public function test_valid_lead_is_captured_into_attribution(): void
    {
        $response = $this->post('/leads', [
            'name' => 'Dana Owner',
            'email' => 'dana@example.com',
            'business' => 'Dana Exteriors',
            'plan' => 'pro',
        ]);

        $response->assertStatus(302)->assertSessionHas('lead_ok', 'Dana Owner');

        $rows = app(AttributionStore::class)->all();
        $this->assertCount(1, $rows);
        $row = array_values($rows)[0];
        $this->assertSame('dana@example.com', $row->leadId);
        $this->assertSame('website', $row->utmSource);
    }

    public function test_invalid_lead_is_rejected_and_records_nothing(): void
    {
        $response = $this->post('/leads', [
            'name' => 'No Email',
        ]);

        $response->assertStatus(302)->assertSessionHasErrors('email');
        $this->assertCount(0, app(AttributionStore::class)->all());
    }

    public function test_lead_is_forwarded_to_crm_when_configured(): void
    {
        Http::fake();
        config(['funnel.lead_forward_url' => 'https://crm.example.com/hook']);

        $this->post('/leads', [
            'name' => 'Pat Lead',
            'email' => 'pat@example.com',
        ])->assertSessionHas('lead_ok');

        Http::assertSent(fn ($request) => $request->url() === 'https://crm.example.com/hook'
            && $request['email'] === 'pat@example.com');
    }
}
