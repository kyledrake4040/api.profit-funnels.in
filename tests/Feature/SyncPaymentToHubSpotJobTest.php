<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncPaymentToHubSpot;
use App\Models\Payment;
use App\Services\HubSpotClient;
use Mockery;
use Tests\TestCase;

class SyncPaymentToHubSpotJobTest extends TestCase
{
    public function test_handle_delegates_to_the_hubspot_client(): void
    {
        $payment = new Payment(['customer_email' => 'buyer@example.com', 'amount' => 69900]);

        $hubspot = Mockery::mock(HubSpotClient::class);
        $hubspot->shouldReceive('syncPayment')->once()->with($payment);

        (new SyncPaymentToHubSpot($payment))->handle($hubspot);

        // Mockery expectation verified on teardown.
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
