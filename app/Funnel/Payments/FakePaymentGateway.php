<?php

declare(strict_types=1);

namespace App\Funnel\Payments;

/**
 * Offline gateway for demos/tests — produces a realistic-looking checkout link
 * without contacting Stripe. Lets the full money-flow run with zero setup.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function createCheckout(string $productName, int $amountCents, string $currency, string $successUrl, string $cancelUrl): CheckoutLink
    {
        $id = 'cs_test_' . substr(md5($productName . $amountCents . microtime()), 0, 24);

        return new CheckoutLink(
            id: $id,
            url: 'https://checkout.stripe.com/c/pay/' . $id,
            amountCents: $amountCents,
            currency: $currency,
            live: false,
        );
    }
}
