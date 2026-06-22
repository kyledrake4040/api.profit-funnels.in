<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncPaymentToHubSpot;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->secret]);
    }

    /** @param array<string,mixed> $event */
    private function send(array $event, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($event);
        $ts = time();
        $signature ??= 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $this->secret);

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function checkoutEvent(string $id = 'evt_1'): array
    {
        return [
            'id' => $id,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_1',
                'payment_intent' => 'pi_1',
                'amount_total' => 69900,
                'currency' => 'usd',
                'payment_status' => 'paid',
                'customer_details' => ['email' => 'buyer@example.com', 'name' => 'Jane Buyer'],
            ]],
        ];
    }

    /** @param array<string,mixed> $overrides */
    private function subscriptionCheckoutEvent(string $id, array $overrides = []): array
    {
        return [
            'id' => $id,
            'type' => 'checkout.session.completed',
            'data' => ['object' => array_merge([
                'id' => 'cs_sub_1',
                'mode' => 'subscription',
                'subscription' => 'sub_123',
                'amount_total' => 9900,
                'currency' => 'usd',
                'payment_status' => 'paid',
                'metadata' => ['plan' => 'starter'],
                'customer_details' => ['email' => 'newclient@example.com', 'name' => 'New Client'],
            ], $overrides)],
        ];
    }

    public function test_missing_secret_returns_500(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->send($this->checkoutEvent())->assertStatus(500);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Queue::fake();

        $this->send($this->checkoutEvent(), 't=' . time() . ',v1=deadbeef')->assertStatus(400);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_valid_event_records_payment_and_queues_hubspot_sync(): void
    {
        Queue::fake();

        $this->send($this->checkoutEvent())->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('payments', [
            'stripe_event_id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'customer_email' => 'buyer@example.com',
            'amount' => 69900,
        ]);
        Queue::assertPushed(SyncPaymentToHubSpot::class, 1);
    }

    public function test_duplicate_event_is_ignored(): void
    {
        Queue::fake();

        $this->send($this->checkoutEvent('evt_dup'))->assertOk();
        $this->send($this->checkoutEvent('evt_dup'))->assertOk()->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('payments', 1);
        Queue::assertPushed(SyncPaymentToHubSpot::class, 1);
    }

    public function test_subscription_checkout_provisions_user_and_subscription(): void
    {
        Queue::fake();
        $this->seed(PlanSeeder::class);

        $this->send($this->subscriptionCheckoutEvent('evt_sub_1'))->assertOk();

        $user = User::where('email', 'newclient@example.com')->first();
        $this->assertNotNull($user, 'A user should be provisioned for the paying customer.');
        $this->assertSame('New Client', $user->name);

        $this->assertDatabaseHas('subscriptions', [
            'user_id'           => $user->id,
            'plan_id'           => Plan::where('slug', 'starter')->value('id'),
            'status'            => 'Active',
            'gateway'           => 'stripe',
            'gateway_reference' => 'sub_123',
        ]);
    }

    public function test_subscription_provisioning_is_idempotent_on_stripe_subscription_id(): void
    {
        Queue::fake();
        $this->seed(PlanSeeder::class);

        // Two distinct Stripe events for the same underlying subscription.
        $this->send($this->subscriptionCheckoutEvent('evt_a'))->assertOk();
        $this->send($this->subscriptionCheckoutEvent('evt_b'))->assertOk();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(1, User::where('email', 'newclient@example.com')->count());
    }

    public function test_one_time_payment_does_not_provision_a_subscription(): void
    {
        Queue::fake();
        $this->seed(PlanSeeder::class);

        // The default checkout event has no subscription mode/metadata.
        $this->send($this->checkoutEvent('evt_one_time'))->assertOk();

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseMissing('users', ['email' => 'buyer@example.com']);
    }

    public function test_unknown_plan_slug_is_skipped(): void
    {
        Queue::fake();
        $this->seed(PlanSeeder::class);

        $this->send($this->subscriptionCheckoutEvent('evt_bad', [
            'metadata' => ['plan' => 'no-such-plan'],
        ]))->assertOk();

        $this->assertDatabaseCount('subscriptions', 0);
    }
}
