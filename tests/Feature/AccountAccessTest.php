<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AccountAccessTest extends TestCase
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

    private function agencyFor(User $owner, string $slug): Agency
    {
        return Agency::create([
            'owner_id' => $owner->id,
            'name'     => ucfirst($slug),
            'slug'     => $slug,
            'status'   => config('custom.agency.status_active'),
        ]);
    }

    public function test_user_only_sees_accounts_they_own_or_belong_to(): void
    {
        $owner = $this->user('owner@example.com');
        $agency = $this->agencyFor($owner, 'mine');
        $mine = $agency->accounts()->create(['name' => 'Mine', 'slug' => 'mine-acct']);

        // An account under someone else's agency that I'm a member of.
        $other = $this->agencyFor($this->user('other@example.com'), 'other');
        $shared = $other->accounts()->create(['name' => 'Shared', 'slug' => 'shared-acct']);
        $shared->members()->attach($owner->id, ['role' => config('custom.account.role_user')]);

        // An account I have nothing to do with.
        $other->accounts()->create(['name' => 'Hidden', 'slug' => 'hidden-acct']);

        Passport::actingAs($owner, ['*'], 'api');
        $response = $this->getJson('/api/accounts')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        sort($slugs);
        $this->assertSame(['mine-acct', 'shared-acct'], $slugs);
    }

    public function test_agency_owner_can_view_a_sub_account(): void
    {
        $owner = $this->user('owner@example.com');
        $agency = $this->agencyFor($owner, 'acme');
        $account = $agency->accounts()->create(['name' => 'Client', 'slug' => 'client']);

        Passport::actingAs($owner, ['*'], 'api');
        $this->getJson("/api/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'client');
    }

    public function test_non_member_cannot_view_a_sub_account(): void
    {
        $agency = $this->agencyFor($this->user('owner@example.com'), 'acme');
        $account = $agency->accounts()->create(['name' => 'Client', 'slug' => 'client']);

        $outsider = $this->user('outsider@example.com');
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$account->id}")->assertForbidden();
    }

    public function test_member_can_view_a_sub_account(): void
    {
        $agency = $this->agencyFor($this->user('owner@example.com'), 'acme');
        $account = $agency->accounts()->create(['name' => 'Client', 'slug' => 'client']);

        $member = $this->user('member@example.com');
        $account->members()->attach($member->id, ['role' => config('custom.account.role_admin')]);

        Passport::actingAs($member, ['*'], 'api');
        $this->getJson("/api/accounts/{$account->id}")->assertOk();
    }

    public function test_creating_an_account_requires_owning_the_agency(): void
    {
        $owner = $this->user('owner@example.com');
        $agency = $this->agencyFor($owner, 'acme');

        $stranger = $this->user('stranger@example.com');
        Passport::actingAs($stranger, ['*'], 'api');

        $this->postJson('/api/accounts', [
            'agency_id' => $agency->id,
            'name'      => 'Sneaky',
        ])->assertForbidden();

        $this->assertDatabaseMissing('accounts', ['name' => 'Sneaky']);
    }

    public function test_owner_can_create_an_account_under_their_agency(): void
    {
        $owner = $this->user('owner@example.com');
        $agency = $this->agencyFor($owner, 'acme');

        Passport::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/accounts', [
            'agency_id' => $agency->id,
            'name'      => 'New Client',
        ])->assertCreated()->assertJsonPath('data.slug', 'new-client');

        $this->assertDatabaseHas('accounts', [
            'agency_id' => $agency->id,
            'name'      => 'New Client',
        ]);
    }
}
