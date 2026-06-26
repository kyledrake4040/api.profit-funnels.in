<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\ClaudeClient;
use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DashboardInsightTest extends TestCase
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

        // Some data for the advisor to summarize.
        $this->account->contacts()->create(['first_name' => 'A', 'status' => 'Lead']);
        $pipeline = $this->account->pipelines()->create(['name' => 'Sales', 'slug' => 'sales']);
        $stage = $pipeline->stages()->create(['name' => 'New', 'sort_order' => 0]);
        $this->account->opportunities()->create(['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Deal', 'value' => 1000, 'status' => 'Open']);
    }

    private function fakeClaude(string $key, string $insight = '- Call your new lead today.'): void
    {
        $transport = static fn (array $payload, string $k): array => [
            'status' => 200,
            'body'   => json_encode(['content' => [['type' => 'text', 'text' => $insight]]]),
        ];

        $this->app->instance(ClaudeClient::class, new ClaudeClient($key, 'claude-opus-4-8', $transport));
    }

    public function test_member_gets_an_ai_insight(): void
    {
        $this->fakeClaude('sk-ant-test', "- Follow up with your open deal\n- Call the new lead");
        Passport::actingAs($this->owner, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/dashboard/insight")
            ->assertOk()
            ->assertJsonPath('data.insight', "- Follow up with your open deal\n- Call the new lead");
    }

    public function test_insight_degrades_gracefully_without_a_key(): void
    {
        $this->fakeClaude('');
        Passport::actingAs($this->owner, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/dashboard/insight")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_non_member_cannot_get_an_insight(): void
    {
        $this->fakeClaude('sk-ant-test');
        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/dashboard/insight")->assertForbidden();
    }
}
