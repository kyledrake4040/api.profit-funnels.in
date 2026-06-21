<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\BookingOffer;
use PHPUnit\Framework\TestCase;

final class BookingOfferTest extends TestCase
{
    public function test_price_label_uses_whole_dollars_when_even(): void
    {
        $offer = new BookingOffer('Free Quote', 69900, 'cad', 'mailto:x@y.z');

        self::assertSame('from $699 CAD', $offer->priceLabel());
    }

    public function test_price_label_shows_cents_when_needed(): void
    {
        $offer = new BookingOffer('Free Quote', 69950, 'cad', 'mailto:x@y.z');

        self::assertSame('from $699.50 CAD', $offer->priceLabel());
    }

    public function test_cta_makes_clear_there_is_no_upfront_charge(): void
    {
        $offer = new BookingOffer('Free Quote — House Wash & Power Wash', 69900, 'cad', 'mailto:book@x.z');

        $cta = $offer->cta();
        self::assertStringContainsString('from $699 CAD', $cta);
        self::assertStringContainsString('No payment today', $cta);
        self::assertStringContainsString('mailto:book@x.z', $cta);
    }

    public function test_caption_footer_anchors_price_and_payment_terms(): void
    {
        $offer = new BookingOffer('Free Quote', 69900, 'cad', 'mailto:x@y.z');

        $footer = $offer->captionFooter();
        self::assertStringContainsString('from $699 CAD', $footer);
        self::assertStringContainsString('pay after', $footer);
    }
}
