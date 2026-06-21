<?php

declare(strict_types=1);

namespace App\Funnel\Payments;

final class CheckoutLink
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly bool $live,
    ) {
    }
}
