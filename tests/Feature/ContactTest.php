<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name'     => 'User ' . $email,
            'email'    => $email,
            'password' => bcrypt('secret-secret'),
        ]);
    }

    private function accountOwnedBy(User $owner, string $slug): Account
    {
        $agency = Agency::create([
            'owner_id' => $owner->id,
            'name'     => ucfirst($slug),
            'slug'     => $slug . '-agency',
            'status'   => config('custom.agency.status_active'),
        ]);

        return $agency->accounts()->create([
            'name'   => ucfirst($slug),
            'slug'   => $slug . '-account',
            'status' => config('custom.account.status_active'),
        ]);
    }

    public function test_member_can_create_a_contact_in_their_account(): void
    {
        $owner   = $this->user('owner@example.com');
        $account = $this->accountOwnedBy($owner, 'acme');

        Passport::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/accounts/{$account->id}/contacts", [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@lead.com',
        ])->assertCreated()
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.status', 'Lead');

        $this->assertDatabaseHas('contacts', [
            'account_id' => $account->id,
            'email'      => 'jane@lead.com',
        ]);
    }

    public function test_contacts_are_scoped_to_their_account(): void
    {
        $owner = $this->user('owner@example.com');
        $a = $this->accountOwnedBy($owner, 'alpha');
        $b = $this->accountOwnedBy($owner, 'beta');

        $a->contacts()->create(['first_name' => 'Alpha Lead']);
        $b->contacts()->create(['first_name' => 'Beta Lead']);

        Passport::actingAs($owner, ['*'], 'api');
        $response = $this->getJson("/api/accounts/{$a->id}/contacts")->assertOk();

        $names = collect($response->json('data'))->pluck('first_name')->all();
        $this->assertSame(['Alpha Lead'], $names);
    }

    public function test_non_member_cannot_list_contacts(): void
    {
        $account = $this->accountOwnedBy($this->user('owner@example.com'), 'acme');

        Passport::actingAs($this->user('outsider@example.com'), ['*'], 'api');
        $this->getJson("/api/accounts/{$account->id}/contacts")->assertForbidden();
    }

    public function test_a_contact_from_another_account_is_not_reachable(): void
    {
        $owner = $this->user('owner@example.com');
        $a = $this->accountOwnedBy($owner, 'alpha');
        $b = $this->accountOwnedBy($owner, 'beta');

        $bContact = $b->contacts()->create(['first_name' => 'Beta Lead']);

        Passport::actingAs($owner, ['*'], 'api');
        // Asking for account A's URL but B's contact id → 404 (scoped lookup).
        $this->getJson("/api/accounts/{$a->id}/contacts/{$bContact->id}")->assertNotFound();
    }

    public function test_member_can_update_and_delete_a_contact(): void
    {
        $owner   = $this->user('owner@example.com');
        $account = $this->accountOwnedBy($owner, 'acme');
        $contact = $account->contacts()->create(['first_name' => 'Jane', 'status' => 'Lead']);

        Passport::actingAs($owner, ['*'], 'api');

        $this->putJson("/api/accounts/{$account->id}/contacts/{$contact->id}", [
            'first_name' => 'Jane',
            'status'     => 'Customer',
        ])->assertOk()->assertJsonPath('data.status', 'Customer');

        $this->deleteJson("/api/accounts/{$account->id}/contacts/{$contact->id}")->assertOk();
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_index_supports_status_and_search_filters(): void
    {
        $owner   = $this->user('owner@example.com');
        $account = $this->accountOwnedBy($owner, 'acme');
        $account->contacts()->create(['first_name' => 'Paying', 'status' => 'Customer', 'company' => 'Globex']);
        $account->contacts()->create(['first_name' => 'Cold', 'status' => 'Lead', 'company' => 'Initech']);

        Passport::actingAs($owner, ['*'], 'api');

        $byStatus = $this->getJson("/api/accounts/{$account->id}/contacts?status=Customer")->assertOk();
        $this->assertCount(1, $byStatus->json('data'));

        $bySearch = $this->getJson("/api/accounts/{$account->id}/contacts?q=Initech")->assertOk();
        $this->assertSame('Cold', $bySearch->json('data.0.first_name'));
    }
}
