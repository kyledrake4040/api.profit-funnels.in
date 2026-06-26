<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class JobTest extends TestCase
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

    public function test_can_schedule_a_job_for_a_contact(): void
    {
        $contact = $this->account->contacts()->create(['first_name' => 'Sarah', 'last_name' => 'Mills']);

        $this->postJson("/api/accounts/{$this->account->id}/jobs", [
            'contact_id'   => $contact->id,
            'title'        => 'Exterior repaint',
            'scheduled_at' => '2026-07-01 09:00:00',
            'value'        => 4800,
            'address'      => '12 Water St, Charlottetown PE',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Exterior repaint')
            ->assertJsonPath('data.status', 'Scheduled');

        $this->assertDatabaseHas('service_jobs', [
            'account_id' => $this->account->id,
            'title'      => 'Exterior repaint',
            'value'      => 4800,
        ]);
    }

    public function test_marking_a_job_completed_stamps_completed_at(): void
    {
        $job = $this->account->jobs()->create(['title' => 'Deck stain', 'status' => 'Scheduled']);
        $this->assertNull($job->completed_at);

        $this->putJson("/api/accounts/{$this->account->id}/jobs/{$job->id}", [
            'title'  => 'Deck stain',
            'status' => 'Completed',
        ])->assertOk()->assertJsonPath('data.status', 'Completed');

        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_jobs_can_be_filtered_by_status(): void
    {
        $this->account->jobs()->create(['title' => 'Booked', 'status' => 'Scheduled']);
        $this->account->jobs()->create(['title' => 'Done', 'status' => 'Completed', 'completed_at' => now()]);

        $response = $this->getJson("/api/accounts/{$this->account->id}/jobs?status=Completed")->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Done', $response->json('data.0.title'));
    }

    public function test_cannot_link_a_contact_from_another_account(): void
    {
        $otherAgency = Agency::create(['owner_id' => $this->owner->id, 'name' => 'A2', 'slug' => 'a2', 'status' => 'Active']);
        $otherAccount = $otherAgency->accounts()->create(['name' => 'Other', 'slug' => 'other', 'status' => 'Active']);
        $foreign = $otherAccount->contacts()->create(['first_name' => 'Foreign']);

        $this->postJson("/api/accounts/{$this->account->id}/jobs", [
            'contact_id' => $foreign->id,
            'title'      => 'Sneaky',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('service_jobs', ['title' => 'Sneaky']);
    }

    public function test_jobs_are_scoped_and_member_guarded(): void
    {
        $this->account->jobs()->create(['title' => 'Private']);

        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/jobs")->assertForbidden();
    }
}
