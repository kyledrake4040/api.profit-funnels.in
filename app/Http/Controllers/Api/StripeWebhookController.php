<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\HubSpotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
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

        // Push the order into HubSpot as a contact + deal so fulfillment
        // and follow-up happen in the CRM. Failures are handled internally
        // and never block the webhook response.
        app(HubSpotClient::class)->syncPayment($payment);
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
