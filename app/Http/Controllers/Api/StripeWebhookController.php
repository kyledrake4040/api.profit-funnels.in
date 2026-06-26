<?php

namespace App\Http\Controllers\Api;

use App\Funnel\Payments\SubscriptionProvisioner;
use App\Http\Controllers\Controller;
use App\Jobs\SyncPaymentToHubSpot;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly SubscriptionProvisioner $provisioner)
    {
    }

    /**
     * Receive and process Stripe webhook events.
     *
     * Verifies the Stripe-Signature header against the configured webhook
     * signing secret, then records the relevant payment events. Events are
     * stored idempotently keyed on the Stripe event id so Stripe's automatic
     * retries never create duplicate records.
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret)) {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json(['error' => 'Webhook not configured.'], 500);
        }

        if (! $this->signatureIsValid($payload, $signature, $secret)) {
            Log::warning('Stripe webhook signature verification failed.');

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || empty($event['id']) || empty($event['type'])) {
            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        // Idempotency: if we have already stored this event, acknowledge and stop.
        if (Payment::where('stripe_event_id', $event['id'])->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $this->process($event);

        return response()->json(['received' => true]);
    }

    /**
     * Verify the Stripe-Signature header.
     *
     * Implements Stripe's scheme: the signed payload is "{timestamp}.{body}"
     * hashed with HMAC-SHA256 using the endpoint signing secret. Tolerance
     * guards against replay attacks.
     *
     * @param  string       $payload
     * @param  string|null  $header
     * @param  string       $secret
     * @param  int          $tolerance  seconds
     *
     * @return bool
     */
    protected function signatureIsValid($payload, $header, $secret, $tolerance = 300): bool
    {
        if (empty($header)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pieces = explode('=', $part, 2);

            if (count($pieces) !== 2) {
                continue;
            }

            [$key, $value] = $pieces;

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || empty($signatures)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist the event and trigger any follow-up handling.
     *
     * @param  array  $event
     *
     * @return void
     */
    protected function process(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        $payment = Payment::create([
            'stripe_event_id' => $event['id'],
            'type' => $event['type'],
            'checkout_session_id' => $event['type'] === 'checkout.session.completed' ? ($object['id'] ?? null) : null,
            'payment_intent_id' => $object['payment_intent'] ?? ($object['id'] ?? null),
            'customer_email' => $this->extractEmail($object),
            'customer_name' => data_get($object, 'customer_details.name') ?? data_get($object, 'billing_details.name'),
            'description' => $object['description'] ?? null,
            'amount' => $object['amount_total'] ?? $object['amount'] ?? null,
            'currency' => $object['currency'] ?? null,
            'status' => $object['status'] ?? $object['payment_status'] ?? null,
            'payload' => $event,
        ]);

        Log::info('Stripe payment event recorded.', [
            'event' => $event['type'],
            'payment_id' => $payment->id,
            'amount' => $payment->formatted_amount,
            'email' => $payment->customer_email,
        ]);

        // Stand up the customer's account + subscription the moment their first
        // payment clears. Best-effort: a provisioning hiccup must never fail the
        // webhook (which would trigger Stripe retries) — the Payment row above is
        // the durable record to reconcile from. The provisioner is idempotent.
        if ($event['type'] === 'checkout.session.completed') {
            try {
                $this->provisioner->provisionFromSession($object);
            } catch (Throwable $e) {
                Log::error('Subscription provisioning failed.', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // A client just paid an invoice online: flip it to Paid. Best-effort —
        // the Payment row above is the durable record, so a hiccup here never
        // fails the webhook (which would trigger Stripe retries).
        if ($event['type'] === 'checkout.session.completed') {
            $this->markInvoicePaid($object);
        }

        // Fan out on the events that matter for fulfillment.
        switch ($event['type']) {
            case 'checkout.session.completed':
            case 'invoice.paid':
                $this->onPaymentSucceeded($payment);
                break;
            case 'payment_intent.payment_failed':
                Log::warning('Stripe payment failed.', ['payment_id' => $payment->id]);
                break;
        }
    }

    /**
     * Mark an invoice paid from a checkout session's metadata.invoice_id.
     *
     * @param  array  $object  the Stripe checkout.session object
     *
     * @return void
     */
    protected function markInvoicePaid(array $object): void
    {
        $invoiceId = data_get($object, 'metadata.invoice_id');

        if (empty($invoiceId)) {
            return;
        }

        try {
            $invoice = \App\Models\Invoice::find($invoiceId);

            if ($invoice !== null && ! $invoice->isPaid()) {
                $invoice->markPaid();
                Log::info('Invoice marked paid via Stripe checkout.', ['invoice_id' => $invoice->id]);
            }
        } catch (Throwable $e) {
            Log::error('Marking invoice paid from webhook failed.', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hook for post-purchase fulfillment (onboarding email, CRM, etc.).
     *
     * Kept intentionally lightweight; wire in notifications/jobs here.
     *
     * @param  Payment  $payment
     *
     * @return void
     */
    protected function onPaymentSucceeded(Payment $payment): void
    {
        Log::info('New paid order ready for fulfillment.', [
            'payment_id' => $payment->id,
            'customer' => $payment->customer_email,
            'amount' => $payment->formatted_amount,
        ]);

        // Push the order into HubSpot off the request thread, so a slow or
        // failing CRM call never delays the webhook ack (and triggers Stripe
        // retries). Runs inline only when QUEUE_CONNECTION=sync.
        SyncPaymentToHubSpot::dispatch($payment);
    }

    /**
     * Pull the customer email out of the various Stripe object shapes.
     *
     * @param  array  $object
     *
     * @return string|null
     */
    protected function extractEmail(array $object)
    {
        return data_get($object, 'customer_details.email')
            ?? data_get($object, 'billing_details.email')
            ?? ($object['customer_email'] ?? null)
            ?? ($object['receipt_email'] ?? null);
    }
}
