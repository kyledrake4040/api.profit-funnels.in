<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_funnel(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/funnels', [
            'name'        => 'Webinar Funnel',
            'description' => 'Drives registrations to the weekly webinar.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Webinar Funnel')
            ->assertJsonPath('data.slug', 'webinar-funnel');

        $this->assertDatabaseHas('funnels', [
            'user_id' => $user->id,
            'slug'    => 'webinar-funnel',
        ]);
    }

    public function test_index_only_returns_the_authenticated_users_funnels(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Funnel::create(['user_id' => $user->id, 'name' => 'Mine', 'slug' => 'mine']);
        Funnel::create(['user_id' => $other->id, 'name' => 'Theirs', 'slug' => 'theirs']);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/funnels');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'mine');
    }

    public function test_user_cannot_access_another_users_funnel(): void
    {
        $user   = User::factory()->create();
        $other  = User::factory()->create();
        $funnel = Funnel::create(['user_id' => $other->id, 'name' => 'Theirs', 'slug' => 'theirs']);

        Passport::actingAs($user, ['*'], 'api');

        $this->getJson('/api/funnels/' . $funnel->id)->assertStatus(404);
    }
}
