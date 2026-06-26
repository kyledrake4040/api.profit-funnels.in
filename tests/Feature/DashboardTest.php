<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner@example.com',
            'password' => bcrypt('secret-secret'),
        ]);

        $agency = Agency::create([
            'owner_id' => $this->owner->id,
            'name'     => 'Acme',
            'slug'     => 'acme',
            'status'   => config('custom.agency.status_active'),
        ]);

        $this->account = $agency->accounts()->create([
            'name'   => 'Client',
            'slug'   => 'client',
            'status' => config('custom.account.status_active'),
        ]);

        Passport::actingAs($this->owner, ['*'], 'api');
    }

    public function test_dashboard_rolls_up_contacts_and_pipeline_value(): void
    {
        $this->account->contacts()->create(['first_name' => 'Lead A', 'status' => 'Lead']);
        $this->account->contacts()->create(['first_name' => 'Lead B', 'status' => 'Lead']);
        $this->account->contacts()->create(['first_name' => 'Cust', 'status' => 'Customer']);

        $pipeline = $this->account->pipelines()->create(['name' => 'Sales', 'slug' => 'sales']);
        $stage = $pipeline->stages()->create(['name' => 'New', 'sort_order' => 0]);

        $this->account->opportunities()->create(['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Open 1', 'value' => 1000, 'status' => 'Open']);
        $this->account->opportunities()->create(['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Open 2', 'value' => 1500, 'status' => 'Open']);
        $this->account->opportunities()->create(['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Won 1', 'value' => 5000, 'status' => 'Won']);

        $response = $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertOk();

        $response->assertJsonPath('data.contacts.total', 3)
            ->assertJsonPath('data.contacts.by_status.Lead', 2)
            ->assertJsonPath('data.contacts.by_status.Customer', 1)
            ->assertJsonPath('data.opportunities.open_count', 2)
            ->assertJsonPath('data.opportunities.open_value', 2500)
            ->assertJsonPath('data.opportunities.won_count', 1)
            ->assertJsonPath('data.opportunities.won_value', 5000)
            ->assertJsonPath('data.pipelines', 1);

        $this->assertCount(3, $response->json('data.recent_opportunities'));
    }

    public function test_dashboard_is_scoped_and_member_guarded(): void
    {
        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertForbidden();
    }
}
