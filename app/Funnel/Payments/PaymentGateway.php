<?php

declare(strict_types=1);

namespace App\Funnel\Payments;

interface PaymentGateway
{
    /**
     * Create a hosted checkout link for the offer.
     *
     * @return CheckoutLink
     */
    public function createCheckout(string $productName, int $amountCents, string $currency, string $successUrl, string $cancelUrl): CheckoutLink;
}
