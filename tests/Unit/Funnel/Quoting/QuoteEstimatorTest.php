<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel\Quoting;

use App\Funnel\Quoting\MarketRates;
use App\Funnel\Quoting\QuoteEstimator;
use App\Funnel\Quoting\QuoteRequest;
use PHPUnit\Framework\TestCase;

/**
 * The estimator must price at the middle of the market, respect the minimum,
 * estimate area sensibly from partial information, and be honest about how
 * confident that estimate is.
 */
final class QuoteEstimatorTest extends TestCase
{
    private function estimator(int $minimumCents = 69900): QuoteEstimator
    {
        return new QuoteEstimator(MarketRates::defaults(), $minimumCents, 'cad');
    }

    public function test_measured_area_is_priced_in_the_middle_of_the_market(): void
    {
        // combo band [0.45, 0.75] × 2000 sq ft → low $900, high $1500, mid $1200.
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            wallAreaSqFt: 2000,
        ));

        self::assertSame('measured', $quote->areaSource);
        self::assertSame('high', $quote->confidence);
        self::assertSame(90000, $quote->marketLowCents);
        self::assertSame(150000, $quote->marketHighCents);
        self::assertSame(120000, $quote->totalCents);
        self::assertFalse($quote->minimumApplied);
        self::assertGreaterThan($quote->marketLowCents, $quote->totalCents);
        self::assertLessThan($quote->marketHighCents, $quote->totalCents);
    }

    public function test_small_job_falls_back_to_the_minimum(): void
    {
        // soft wash mid 0.475 × 200 sq ft ≈ $95, well under the $699 minimum.
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_SOFT_WASH,
            wallAreaSqFt: 200,
        ));

        self::assertTrue($quote->minimumApplied);
        self::assertSame(69900, $quote->totalCents);
    }

    public function test_footprint_and_storeys_produce_a_medium_confidence_estimate(): void
    {
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            storeys: 2,
            footprintSqFt: 1000,
        ));

        self::assertSame('estimated from footprint', $quote->areaSource);
        self::assertSame('medium', $quote->confidence);
        // perimeter ≈ 4·√1000·1.10 ≈ 139 ft, × 2 storeys × 9 ft ≈ 2500 sq ft.
        self::assertGreaterThan(2200, $quote->areaSqFt);
        self::assertLessThan(2800, $quote->areaSqFt);
    }

    public function test_size_category_is_low_confidence(): void
    {
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_POWER_WASH,
            sizeCategory: QuoteRequest::SIZE_MEDIUM,
        ));

        self::assertSame('estimated from size', $quote->areaSource);
        self::assertSame('low', $quote->confidence);
        self::assertSame(2000, $quote->areaSqFt);
    }

    public function test_photos_only_give_a_low_confidence_estimate_with_a_caveat(): void
    {
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            photos: ['front.jpg', 'back.jpg', 'left.jpg', 'right.jpg'],
        ));

        self::assertSame('low', $quote->confidence);
        self::assertNotEmpty($quote->notes);
        self::assertStringContainsString('4 photo(s)', implode(' ', $quote->notes));
    }

    public function test_difficulty_conditions_raise_the_price(): void
    {
        $base = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            wallAreaSqFt: 2000,
        ));
        $harder = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            wallAreaSqFt: 2000,
            conditions: ['heavy_algae', 'high_access'],
        ));

        self::assertGreaterThan($base->totalCents, $harder->totalCents);
    }

    public function test_unknown_conditions_are_ignored(): void
    {
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            wallAreaSqFt: 2000,
            conditions: ['made_up_flag'],
        ));

        self::assertSame(120000, $quote->totalCents);
    }

    public function test_totals_snap_to_the_nearest_ten_dollars(): void
    {
        // Choose an area whose raw mid-price is not a round $10 value.
        $quote = $this->estimator()->estimate(new QuoteRequest(
            service: QuoteRequest::SERVICE_COMBO,
            wallAreaSqFt: 1733,
        ));

        self::assertSame(0, $quote->totalCents % 1000);
    }

    public function test_unknown_service_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->estimator()->estimate(new QuoteRequest(service: 'gutter_cleaning', wallAreaSqFt: 2000));
    }

    public function test_no_area_information_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->estimator()->estimate(new QuoteRequest(service: QuoteRequest::SERVICE_COMBO));
    }
}
