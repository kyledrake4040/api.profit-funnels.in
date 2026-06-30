<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\JsonAttributionStore;
use App\Models\User;
use Tests\TestCase;

class FunnelDashboardTest extends TestCase
{
    private string $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = base_path('storage/funnel/attribution.json');
        @unlink($this->store);

        // These tests seed and assert through the JSON store and intentionally
        // do not migrate a database. Pin the driver and path so the suite is
        // hermetic regardless of the ambient .env (which ships
        // FUNNEL_ATTRIBUTION_DRIVER=eloquent for production).
        config([
            'funnel.attribution_driver' => 'json',
            'funnel.json_store_path' => $this->store,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->store);
        parent::tearDown();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/funnel/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_funnel_and_other_numbers(): void
    {
        $recorder = new AttributionRecorder(new JsonAttributionStore($this->store));
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'a']);
        $recorder->recordLead(['utm_source' => 'google', 'contact_id' => 'b']);
        $recorder->recordPaidInvoice(['customer_id' => 'a', 'amount' => 699.00]);

        $response = $this->actingAs(new User())->get('/funnel/dashboard?days=30');

        $response->assertOk()
            ->assertSee('Funnel Attribution')
            ->assertSee('$699.00')
            ->assertSee('50%'); // 1 of 2 leads is funnel
    }

    public function test_json_format_returns_the_summary(): void
    {
        $recorder = new AttributionRecorder(new JsonAttributionStore($this->store));
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'a']);

        $response = $this->actingAs(new User())->getJson('/funnel/dashboard?format=json&days=7');

        $response->assertOk()
            ->assertJsonPath('funnel.leads', 1)
            ->assertJsonPath('days', 7);
    }
}
