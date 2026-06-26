<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiteTest extends TestCase
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
    }

    public function test_owner_can_create_and_update_the_site(): void
    {
        Passport::actingAs($this->owner, ['*'], 'api');

        $this->putJson("/api/accounts/{$this->account->id}/site", [
            'business_name' => 'Island Painters',
            'headline'      => 'Fresh coats, fair prices',
            'services'      => ['Interior', 'Exterior', 'Decks'],
            'city'          => 'Charlottetown',
            'published'     => true,
        ])->assertOk()
            ->assertJsonPath('data.business_name', 'Island Painters')
            ->assertJsonPath('data.slug', 'island-painters')
            ->assertJsonPath('data.published', true);

        // Upsert again updates rather than duplicates.
        $this->putJson("/api/accounts/{$this->account->id}/site", [
            'business_name' => 'Island Painters Co',
            'published'     => true,
        ])->assertOk()->assertJsonPath('data.business_name', 'Island Painters Co');

        $this->assertDatabaseCount('sites', 1);
    }

    public function test_published_site_renders_publicly(): void
    {
        $this->account->site()->create([
            'slug' => 'island-painters', 'business_name' => 'Island Painters',
            'headline' => 'Fresh coats', 'services' => ['Exterior'], 'published' => true,
        ]);

        $this->get('/s/island-painters')
            ->assertOk()
            ->assertSee('Island Painters')
            ->assertSee('Fresh coats')
            ->assertSee('Request a quote');
    }

    public function test_unpublished_site_is_not_found(): void
    {
        $this->account->site()->create([
            'slug' => 'hidden', 'business_name' => 'Hidden', 'published' => false,
        ]);

        $this->get('/s/hidden')->assertNotFound();
    }

    public function test_public_lead_becomes_a_contact_in_the_account(): void
    {
        $this->account->site()->create([
            'slug' => 'island-painters', 'business_name' => 'Island Painters', 'published' => true,
        ]);

        $this->post('/s/island-painters/lead', [
            'name'    => 'Sarah Mills',
            'email'   => 'sarah@example.com',
            'phone'   => '902-555-0100',
            'message' => 'Need my deck stained',
        ])->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'account_id' => $this->account->id,
            'first_name' => 'Sarah',
            'last_name'  => 'Mills',
            'email'      => 'sarah@example.com',
            'source'     => 'Website',
            'status'     => 'Lead',
        ]);
    }

    public function test_a_site_lead_fires_contact_created_automations(): void
    {
        $this->account->site()->create([
            'slug' => 'auto-site', 'business_name' => 'Auto', 'published' => true,
        ]);
        $this->account->automations()->create([
            'name' => 'Tag web leads', 'trigger_event' => 'contact.created', 'is_active' => true,
        ])->actions()->create(['type' => 'add_tag', 'config' => ['tag' => 'web-lead'], 'sort_order' => 0]);

        $this->post('/s/auto-site/lead', ['name' => 'New Lead'])->assertRedirect();

        $contact = $this->account->contacts()->first();
        $this->assertContains('web-lead', $contact->tags ?? []);
    }

    public function test_site_management_is_member_guarded(): void
    {
        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/site")->assertForbidden();
    }
}
