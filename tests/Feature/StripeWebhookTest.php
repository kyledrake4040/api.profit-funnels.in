<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncPaymentToHubSpot;
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
}
