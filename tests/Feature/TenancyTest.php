<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create([
            'name'     => 'Owner ' . $email,
            'email'    => $email,
            'password' => bcrypt('secret-secret'),
        ]);
    }

    private function makeAgency(User $owner, string $slug): Agency
    {
        return Agency::create([
            'owner_id' => $owner->id,
            'name'     => ucfirst($slug) . ' Agency',
            'slug'     => $slug,
            'status'   => config('custom.agency.status_active'),
        ]);
    }

    public function test_an_agency_belongs_to_its_owner_and_has_accounts(): void
    {
        $owner  = $this->makeUser('reseller@example.com');
        $agency = $this->makeAgency($owner, 'gulfcoast');

        $agency->accounts()->create(['name' => 'Client A', 'slug' => 'client-a']);
        $agency->accounts()->create(['name' => 'Client B', 'slug' => 'client-b']);

        $this->assertSame($owner->id, $agency->owner->id);
        $this->assertCount(2, $agency->accounts);
        $this->assertTrue($agency->isActive());
        $this->assertTrue($owner->ownedAgencies->contains($agency));
    }

    public function test_an_account_has_members_each_with_a_role(): void
    {
        $owner   = $this->makeUser('agencyowner@example.com');
        $agency  = $this->makeAgency($owner, 'acme');
        $account = $agency->accounts()->create(['name' => 'Client', 'slug' => 'client']);

        $admin  = $this->makeUser('admin@example.com');
        $member = $this->makeUser('member@example.com');

        $account->members()->attach($admin->id, ['role' => config('custom.account.role_admin')]);
        $account->members()->attach($member->id, ['role' => config('custom.account.role_user')]);

        $this->assertCount(2, $account->members);
        $this->assertSame(
            'Admin',
            $account->members()->where('users.id', $admin->id)->first()->pivot->role,
        );
        // The membership is visible from the user side too.
        $this->assertTrue($member->accounts->contains($account));
    }

    public function test_accounts_are_isolated_to_their_agency(): void
    {
        $agencyA = $this->makeAgency($this->makeUser('a@example.com'), 'agency-a');
        $agencyB = $this->makeAgency($this->makeUser('b@example.com'), 'agency-b');

        $agencyA->accounts()->create(['name' => 'A1', 'slug' => 'a1']);
        $agencyB->accounts()->create(['name' => 'B1', 'slug' => 'b1']);
        $agencyB->accounts()->create(['name' => 'B2', 'slug' => 'b2']);

        $this->assertCount(1, $agencyA->accounts);
        $this->assertCount(2, $agencyB->accounts);
        $this->assertSame(
            $agencyB->id,
            Account::where('slug', 'b1')->first()->agency_id,
        );
    }

    public function test_deleting_an_agency_cascades_to_its_accounts(): void
    {
        $agency  = $this->makeAgency($this->makeUser('owner@example.com'), 'temp');
        $account = $agency->accounts()->create(['name' => 'Client', 'slug' => 'temp-client']);

        // Force delete to exercise the FK cascade (soft delete would keep the row).
        $agency->forceDelete();

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }
}
