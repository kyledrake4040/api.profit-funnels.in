<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel\Quoting;

use App\Funnel\FunnelConfig;
use App\Funnel\Quoting\MarketRates;
use App\Funnel\Quoting\QuoteEstimator;
use App\Funnel\Quoting\QuoteLetter;
use App\Funnel\Quoting\QuoteRequest;
use PHPUnit\Framework\TestCase;

/**
 * The written quote is what the customer actually reads, so it must carry the
 * name, the price, and the funnel's core promise (no payment up front), and it
 * must flag when a price is only an estimate.
 */
final class QuoteLetterTest extends TestCase
{
    private function compose(QuoteRequest $request): string
    {
        $config = FunnelConfig::fromEnv();
        $quote = (new QuoteEstimator(MarketRates::defaults(), $config->fromPriceCents, $config->currency))
            ->estimate($request);

        return QuoteLetter::compose($config, $request, $quote);
    }

    public function test_letter_includes_name_price_and_no_upfront_promise(): void
    {
        $letter = $this->compose(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            wallAreaSqFt: 2000,
            customerName: 'Jane',
            address: '12 Water St, Charlottetown',
        ));

        self::assertStringContainsString('Hi Jane,', $letter);
        self::assertStringContainsString('$1,200 CAD', $letter);
        self::assertStringContainsString('12 Water St, Charlottetown', $letter);
        self::assertStringContainsString('only pay once the work is done', $letter);
        // A confident (measured) quote shows the market band for transparency.
        self::assertStringContainsString('price fairly in the middle', $letter);
    }

    public function test_letter_flags_estimates_and_greets_generically_without_a_name(): void
    {
        $letter = $this->compose(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            photos: ['front.jpg', 'back.jpg'],
        ));

        self::assertStringContainsString('Hi there,', $letter);
        self::assertStringContainsString('this is an estimate', $letter);
    }
}
