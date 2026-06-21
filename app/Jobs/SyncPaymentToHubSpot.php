<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\HubSpotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a paid order into HubSpot off the request thread, so a slow or failing
 * HubSpot call never delays the Stripe webhook acknowledgement (which would
 * trigger Stripe retries).
 */
class SyncPaymentToHubSpot implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry a few times with backoff for transient HubSpot/network errors. */
    public int $tries = 3;

    /** @return array<int,int> seconds between attempts */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public readonly Payment $payment)
    {
    }

    public function handle(HubSpotClient $hubspot): void
    {
        $hubspot->syncPayment($this->payment);
    }
}
