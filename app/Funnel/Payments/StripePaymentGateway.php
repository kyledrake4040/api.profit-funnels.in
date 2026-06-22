<?php

declare(strict_types=1);

namespace App\Funnel\Payments;

use RuntimeException;

/**
 * Real Stripe Checkout via the REST API (no SDK dependency, just curl).
 * Creates a hosted Checkout Session and returns its URL.
 *
 * Set STRIPE_SECRET (sk_test_... or sk_live_...). Use a test key first; the
 * returned link is a real, payable Stripe page.
 */
final class StripePaymentGateway implements PaymentGateway
{
    /** @param callable|null $transport for tests: fn(string $url, array $fields, string $secret): array{status:int,body:string} */
    public function __construct(
        private readonly string $secret,
        private $transport = null,
    ) {
        if ($secret === '') {
            throw new RuntimeException('STRIPE_SECRET is required for live checkout.');
        }
    }

    public function createCheckout(string $productName, int $amountCents, string $currency, string $successUrl, string $cancelUrl): CheckoutLink
    {
        return $this->request(
            $this->buildFields($productName, $amountCents, $currency, $successUrl, $cancelUrl),
            $amountCents,
            $currency,
        );
    }

    /**
     * Recurring (monthly/yearly) Checkout Session — used to sell the service
     * subscription plans. $interval is a Stripe interval, e.g. "month".
     */
    public function createSubscriptionCheckout(string $productName, int $amountCents, string $currency, string $interval, string $successUrl, string $cancelUrl): CheckoutLink
    {
        return $this->request(
            $this->buildSubscriptionFields($productName, $amountCents, $currency, $interval, $successUrl, $cancelUrl),
            $amountCents,
            $currency,
        );
    }

    /**
     * Create a Checkout Session from form-encoded params and parse the result.
     *
     * @param array<string,string> $fields
     */
    private function request(array $fields, int $amountCents, string $currency): CheckoutLink
    {
        $response = $this->send('https://api.stripe.com/v1/checkout/sessions', $fields);

        $decoded = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || ! is_array($decoded) || ! isset($decoded['url'])) {
            $msg = is_array($decoded) && isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : ('HTTP ' . $response['status']);
            throw new RuntimeException('Stripe checkout failed: ' . $msg);
        }

        return new CheckoutLink(
            id: (string) ($decoded['id'] ?? ''),
            url: (string) $decoded['url'],
            amountCents: $amountCents,
            currency: $currency,
            live: str_starts_with($this->secret, 'sk_live_'),
        );
    }

    /**
     * Stripe form-encoded params for a one-line-item Checkout Session.
     *
     * @return array<string,string>
     */
    public function buildFields(string $productName, int $amountCents, string $currency, string $successUrl, string $cancelUrl): array
    {
        return [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => (string) $amountCents,
            'line_items[0][price_data][product_data][name]' => $productName,
        ];
    }

    /**
     * Stripe params for a recurring subscription Checkout Session (inline
     * price_data so no pre-created Price is needed).
     *
     * @return array<string,string>
     */
    public function buildSubscriptionFields(string $productName, int $amountCents, string $currency, string $interval, string $successUrl, string $cancelUrl): array
    {
        return [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => (string) $amountCents,
            'line_items[0][price_data][recurring][interval]' => $interval,
            'line_items[0][price_data][product_data][name]' => $productName,
        ];
    }

    /**
     * @param array<string,string> $fields
     * @return array{status:int,body:string}
     */
    private function send(string $url, array $fields): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $fields, $this->secret);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_USERPWD => $this->secret . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException($err !== '' ? $err : 'curl error');
        }

        return ['status' => $status, 'body' => (string) $body];
    }
}
