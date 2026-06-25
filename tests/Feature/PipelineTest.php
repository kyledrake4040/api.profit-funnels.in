<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PipelineTest extends TestCase
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

    public function test_creating_a_pipeline_without_stages_seeds_the_defaults(): void
    {
        $response = $this->postJson("/api/accounts/{$this->account->id}/pipelines", [
            'name' => 'Sales',
        ])->assertCreated();

        $stages = collect($response->json('data.stages'))->pluck('name')->all();
        $this->assertSame(config('custom.pipeline.default_stages'), $stages);
        $this->assertSame('sales', $response->json('data.slug'));
    }

    public function test_creating_a_pipeline_with_explicit_stages(): void
    {
        $response = $this->postJson("/api/accounts/{$this->account->id}/pipelines", [
            'name'   => 'Custom',
            'stages' => ['Inbox', 'Working', 'Closed'],
        ])->assertCreated();

        $stages = collect($response->json('data.stages'))->pluck('name')->all();
        $this->assertSame(['Inbox', 'Working', 'Closed'], $stages);
        // Stage order is preserved via sort_order.
        $this->assertSame([0, 1, 2], collect($response->json('data.stages'))->pluck('sort_order')->all());
    }

    public function test_pipelines_are_scoped_to_their_account(): void
    {
        $this->postJson("/api/accounts/{$this->account->id}/pipelines", ['name' => 'Mine'])->assertCreated();

        // A second account under a different owner.
        $other = User::create(['name' => 'O2', 'email' => 'o2@example.com', 'password' => bcrypt('secret-secret')]);
        $otherAgency = Agency::create(['owner_id' => $other->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'Active']);
        $otherAccount = $otherAgency->accounts()->create(['name' => 'Theirs', 'slug' => 'theirs', 'status' => 'Active']);

        Passport::actingAs($other, ['*'], 'api');
        $response = $this->getJson("/api/accounts/{$otherAccount->id}/pipelines")->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
