<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\ClaudeClient;
use App\Models\Account;
use App\Models\Agency;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AiReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Account $account;
    private Contact $contact;

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
            'name'   => 'Gulf Coast Painting',
            'slug'   => 'gulf-coast',
            'status' => config('custom.account.status_active'),
        ]);

        $this->contact = $this->account->contacts()->create([
            'first_name' => 'Sarah',
            'last_name'  => 'Mills',
            'email'      => 'sarah@example.com',
            'notes'      => 'Want my deck stained',
        ]);
    }

    private function fakeClaude(string $key, string $reply = 'Hi Sarah, thanks for reaching out about your deck!'): void
    {
        $transport = static fn (array $payload, string $k): array => [
            'status' => 200,
            'body'   => json_encode(['content' => [['type' => 'text', 'text' => $reply]]]),
        ];

        $this->app->instance(ClaudeClient::class, new ClaudeClient($key, 'claude-opus-4-8', $transport));
    }

    public function test_member_gets_an_ai_drafted_reply(): void
    {
        $this->fakeClaude('sk-ant-test');
        Passport::actingAs($this->owner, ['*'], 'api');

        $this->postJson("/api/accounts/{$this->account->id}/contacts/{$this->contact->id}/ai-reply")
            ->assertOk()
            ->assertJsonPath('data.draft', 'Hi Sarah, thanks for reaching out about your deck!');
    }

    public function test_it_degrades_gracefully_without_an_api_key(): void
    {
        $this->fakeClaude(''); // unconfigured client
        Passport::actingAs($this->owner, ['*'], 'api');

        $this->postJson("/api/accounts/{$this->account->id}/contacts/{$this->contact->id}/ai-reply")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_a_contact_from_another_account_is_not_reachable(): void
    {
        $this->fakeClaude('sk-ant-test');

        $otherAgency = Agency::create(['owner_id' => $this->owner->id, 'name' => 'B', 'slug' => 'b', 'status' => 'Active']);
        $otherAccount = $otherAgency->accounts()->create(['name' => 'Other', 'slug' => 'other', 'status' => 'Active']);

        Passport::actingAs($this->owner, ['*'], 'api');

        // account the user can access, but a contact id from a different account → 404
        $this->postJson("/api/accounts/{$otherAccount->id}/contacts/{$this->contact->id}/ai-reply")
            ->assertNotFound();
    }

    public function test_non_member_cannot_draft_a_reply(): void
    {
        $this->fakeClaude('sk-ant-test');

        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->postJson("/api/accounts/{$this->account->id}/contacts/{$this->contact->id}/ai-reply")
            ->assertForbidden();
    }
}
