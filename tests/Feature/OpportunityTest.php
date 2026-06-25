<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\Contact;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Account $account;
    private Pipeline $pipeline;

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

        $this->pipeline = $this->account->pipelines()->create(['name' => 'Sales', 'slug' => 'sales']);
        foreach (['New', 'Won'] as $i => $name) {
            $this->pipeline->stages()->create(['name' => $name, 'sort_order' => $i]);
        }

        Passport::actingAs($this->owner, ['*'], 'api');
    }

    private function firstStageId(): int
    {
        return (int) $this->pipeline->stages()->orderBy('sort_order')->first()->id;
    }

    public function test_can_create_an_opportunity_in_a_stage(): void
    {
        $contact = $this->account->contacts()->create(['first_name' => 'Lead']);

        $this->postJson("/api/accounts/{$this->account->id}/opportunities", [
            'pipeline_id' => $this->pipeline->id,
            'stage_id'    => $this->firstStageId(),
            'contact_id'  => $contact->id,
            'name'        => 'Big Deal',
            'value'       => 2500,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Big Deal')
            ->assertJsonPath('data.status', 'Open');

        $this->assertDatabaseHas('opportunities', [
            'account_id' => $this->account->id,
            'name'       => 'Big Deal',
            'value'      => 2500,
        ]);
    }

    public function test_cannot_use_a_stage_from_another_pipeline(): void
    {
        // A stage belonging to a different pipeline in the same account.
        $other = $this->account->pipelines()->create(['name' => 'Other', 'slug' => 'other']);
        $foreignStage = $other->stages()->create(['name' => 'X', 'sort_order' => 0]);

        $this->postJson("/api/accounts/{$this->account->id}/opportunities", [
            'pipeline_id' => $this->pipeline->id,
            'stage_id'    => $foreignStage->id,
            'name'        => 'Mismatched',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('opportunities', ['name' => 'Mismatched']);
    }

    public function test_cannot_attach_a_contact_from_another_account(): void
    {
        $otherAgency = Agency::create(['owner_id' => $this->owner->id, 'name' => 'A2', 'slug' => 'a2', 'status' => 'Active']);
        $otherAccount = $otherAgency->accounts()->create(['name' => 'Other', 'slug' => 'other-acct', 'status' => 'Active']);
        $foreignContact = $otherAccount->contacts()->create(['first_name' => 'Foreign']);

        $this->postJson("/api/accounts/{$this->account->id}/opportunities", [
            'pipeline_id' => $this->pipeline->id,
            'stage_id'    => $this->firstStageId(),
            'contact_id'  => $foreignContact->id,
            'name'        => 'Sneaky',
        ])->assertStatus(422);
    }

    public function test_can_move_a_deal_to_a_new_stage_and_mark_won(): void
    {
        $stages = $this->pipeline->stages()->orderBy('sort_order')->get();
        $opp = $this->account->opportunities()->create([
            'pipeline_id' => $this->pipeline->id,
            'stage_id'    => $stages[0]->id,
            'name'        => 'Deal',
            'status'      => 'Open',
        ]);

        $this->putJson("/api/accounts/{$this->account->id}/opportunities/{$opp->id}", [
            'pipeline_id' => $this->pipeline->id,
            'stage_id'    => $stages[1]->id,
            'name'        => 'Deal',
            'status'      => 'Won',
        ])->assertOk()
            ->assertJsonPath('data.status', 'Won')
            ->assertJsonPath('data.stage_id', $stages[1]->id);
    }

    public function test_opportunities_can_be_filtered_by_status(): void
    {
        $stageId = $this->firstStageId();
        $this->account->opportunities()->create(['pipeline_id' => $this->pipeline->id, 'stage_id' => $stageId, 'name' => 'Open one', 'status' => 'Open']);
        $this->account->opportunities()->create(['pipeline_id' => $this->pipeline->id, 'stage_id' => $stageId, 'name' => 'Won one', 'status' => 'Won']);

        $response = $this->getJson("/api/accounts/{$this->account->id}/opportunities?status=Won")->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Won one', $response->json('data.0.name'));
    }

    public function test_non_member_cannot_list_opportunities(): void
    {
        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/opportunities")->assertForbidden();
    }
}
