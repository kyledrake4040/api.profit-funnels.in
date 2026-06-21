<?php

declare(strict_types=1);

namespace App\Funnel;

/**
 * The funnel's conversion step: a FREE quote / booking.
 *
 * Nobody is charged up front. The price is communicated as a "from" anchor
 * (the minimum job, which varies with house size) and the customer only pays
 * once the work is done.
 */
final class BookingOffer
{
    public function __construct(
        public readonly string $name,
        public readonly int $fromPriceCents,
        public readonly string $currency,
        public readonly string $bookingUrl,
        /** What the job actually is, in plain language. */
        public readonly string $description = 'a light chemical wash that kills mildew and lifts dirt, then a power wash and rinse',
        /** Why the price is a range. */
        public readonly string $sizeNote = 'depending on the size of your home',
    ) {
    }

    /** e.g. "from $699 CAD" */
    public function priceLabel(): string
    {
        $amount = $this->fromPriceCents % 100 === 0
            ? (string) intdiv($this->fromPriceCents, 100)
            : number_format($this->fromPriceCents / 100, 2);

        return 'from $' . $amount . ' ' . strtoupper($this->currency);
    }

    /** One-line call to action for captions / bios / pinned comments. */
    public function cta(): string
    {
        return $this->name . ' — ' . $this->description . '. Jobs ' . $this->priceLabel()
            . ' ' . $this->sizeNote . '. No payment today; you only pay once the work is done. 👉 ' . $this->bookingUrl;
    }

    /** Short footer suited to a value-first video caption. */
    public function captionFooter(): string
    {
        return 'Free quote — ' . $this->description . ', ' . $this->priceLabel()
            . ' ' . $this->sizeNote . ', and you only pay after it\'s done.';
    }
}
