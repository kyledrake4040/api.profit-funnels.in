<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AutomationTest extends TestCase
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

    public function test_can_create_an_automation_with_actions(): void
    {
        $this->postJson("/api/accounts/{$this->account->id}/automations", [
            'name'          => 'Tag new leads',
            'trigger_event' => 'contact.created',
            'actions'       => [
                ['type' => 'add_tag', 'config' => ['tag' => 'from-website']],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.trigger_event', 'contact.created')
            ->assertJsonPath('data.actions.0.type', 'add_tag');

        $this->assertDatabaseHas('automations', ['name' => 'Tag new leads']);
        $this->assertDatabaseHas('automation_actions', ['type' => 'add_tag']);
    }

    public function test_contact_created_automation_tags_the_new_contact(): void
    {
        $this->account->automations()->create([
            'name' => 'Tagger', 'trigger_event' => 'contact.created', 'is_active' => true,
        ])->actions()->create(['type' => 'add_tag', 'config' => ['tag' => 'vip'], 'sort_order' => 0]);

        $response = $this->postJson("/api/accounts/{$this->account->id}/contacts", [
            'first_name' => 'Auto', 'last_name' => 'Tagged',
        ])->assertCreated();

        // The response reflects the automation's effect.
        $this->assertContains('vip', $response->json('data.tags'));
    }

    public function test_inactive_automation_does_not_run(): void
    {
        $this->account->automations()->create([
            'name' => 'Off', 'trigger_event' => 'contact.created', 'is_active' => false,
        ])->actions()->create(['type' => 'add_tag', 'config' => ['tag' => 'nope'], 'sort_order' => 0]);

        $response = $this->postJson("/api/accounts/{$this->account->id}/contacts", [
            'first_name' => 'No', 'last_name' => 'Tag',
        ])->assertCreated();

        $this->assertEmpty($response->json('data.tags') ?? []);
    }

    public function test_winning_a_deal_auto_creates_a_job_via_automation(): void
    {
        // The GHL -> Jobber bridge: opportunity.won -> create_job.
        $this->account->automations()->create([
            'name' => 'Won deal books a job', 'trigger_event' => 'opportunity.won', 'is_active' => true,
        ])->actions()->create(['type' => 'create_job', 'config' => ['title' => 'Kickoff visit'], 'sort_order' => 0]);

        $contact  = $this->account->contacts()->create(['first_name' => 'Buyer']);
        $pipeline = $this->account->pipelines()->create(['name' => 'Sales', 'slug' => 'sales']);
        $new  = $pipeline->stages()->create(['name' => 'New', 'sort_order' => 0]);
        $won  = $pipeline->stages()->create(['name' => 'Won', 'sort_order' => 1]);

        $deal = $this->account->opportunities()->create([
            'pipeline_id' => $pipeline->id, 'stage_id' => $new->id, 'contact_id' => $contact->id,
            'name' => 'Big job', 'status' => 'Open',
        ]);

        $this->assertDatabaseCount('service_jobs', 0);

        $this->putJson("/api/accounts/{$this->account->id}/opportunities/{$deal->id}", [
            'pipeline_id' => $pipeline->id, 'stage_id' => $won->id,
            'name' => 'Big job', 'status' => 'Won',
        ])->assertOk();

        $this->assertDatabaseHas('service_jobs', [
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'title'      => 'Kickoff visit',
        ]);
    }

    public function test_won_automation_does_not_refire_on_resave(): void
    {
        $this->account->automations()->create([
            'name' => 'Once', 'trigger_event' => 'opportunity.won', 'is_active' => true,
        ])->actions()->create(['type' => 'create_job', 'config' => ['title' => 'Visit'], 'sort_order' => 0]);

        $contact  = $this->account->contacts()->create(['first_name' => 'Buyer']);
        $pipeline = $this->account->pipelines()->create(['name' => 'Sales', 'slug' => 'sales']);
        $won = $pipeline->stages()->create(['name' => 'Won', 'sort_order' => 0]);
        $deal = $this->account->opportunities()->create([
            'pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'contact_id' => $contact->id,
            'name' => 'Deal', 'status' => 'Won',
        ]);

        // Already Won; saving again must not create a second job.
        $this->putJson("/api/accounts/{$this->account->id}/opportunities/{$deal->id}", [
            'pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Deal', 'status' => 'Won',
        ])->assertOk();

        $this->assertDatabaseCount('service_jobs', 0);
    }

    public function test_automations_are_member_guarded(): void
    {
        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/automations")->assertForbidden();
    }
}
