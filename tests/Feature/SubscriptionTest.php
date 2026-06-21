<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function activePlan(): Plan
    {
        return Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 79.00,
            'currency' => 'USD',
            'interval' => config('custom.plan.interval_monthly'),
            'status' => config('custom.plan.status_active'),
        ]);
    }

    public function test_user_can_subscribe_to_an_active_plan()
    {
        $user = User::factory()->create();
        $plan = $this->activePlan();
        Passport::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/subscriptions', ['plan_id' => $plan->id]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', config('custom.subscription.status_active'));

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => config('custom.subscription.status_active'),
        ]);
    }

    public function test_user_cannot_subscribe_to_an_inactive_plan()
    {
        $user = User::factory()->create();
        $plan = $this->activePlan();
        $plan->update(['status' => config('custom.plan.status_inactive')]);
        Passport::actingAs($user, ['*'], 'api');

        $this->postJson('/api/subscriptions', ['plan_id' => $plan->id])->assertStatus(422);
    }

    public function test_user_can_cancel_their_subscription()
    {
        $user = User::factory()->create();
        $plan = $this->activePlan();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => config('custom.subscription.status_active'),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
        Passport::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/subscriptions/' . $subscription->id . '/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', config('custom.subscription.status_cancelled'));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => config('custom.subscription.status_cancelled'),
        ]);
    }
}
