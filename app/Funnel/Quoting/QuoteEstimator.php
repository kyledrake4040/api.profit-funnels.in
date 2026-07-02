<?php

declare(strict_types=1);

namespace App\Funnel\Quoting;

/**
 * Turns a job description into a fair, mid-market quote.
 *
 * Strategy: work out the wall area, price it at the MIDPOINT of the local
 * market band, nudge for real-world difficulty, then never go below the
 * minimum job price. The result carries the full market range so the price is
 * transparently "in the middle", plus an honest confidence level based on how
 * the area was determined.
 */
final class QuoteEstimator
{
    /** Typical exterior wall height per storey (ft), incl. band/soffit. */
    private const WALL_HEIGHT_PER_STOREY_FT = 9.0;

    /** Real homes aren't perfect squares — bump the derived perimeter a touch. */
    private const FOOTPRINT_SHAPE_FACTOR = 1.10;

    /** Rough wall areas (sq ft) for the size buckets, PEI single-detached homes. */
    private const SIZE_WALL_AREA = [
        QuoteRequest::SIZE_SMALL => 1200,
        QuoteRequest::SIZE_MEDIUM => 2000,
        QuoteRequest::SIZE_LARGE => 2800,
    ];

    /** Difficulty surcharges, applied multiplicatively. */
    private const CONDITION_MULTIPLIER = [
        'heavy_algae' => 1.15,       // heavy mildew/algae — more dwell time & mix
        'high_access' => 1.12,       // 2+ storeys / hard to reach safely
        'difficult_access' => 1.08,  // tight lot, landscaping, decks in the way
    ];

    /** Quotes are snapped to the nearest $10 for a clean number. */
    private const ROUND_TO_CENTS = 1000;

    public function __construct(
        private readonly MarketRates $rates,
        private readonly int $minimumCents,
        private readonly string $currency,
    ) {
    }

    public function estimate(QuoteRequest $request): Quote
    {
        if (! $this->rates->has($request->service)) {
            throw new \InvalidArgumentException("Unknown service \"{$request->service}\".");
        }

        [$area, $source, $confidence, $notes] = $this->area($request);

        $multiplier = 1.0;
        foreach ($request->conditions as $condition) {
            if (isset(self::CONDITION_MULTIPLIER[$condition])) {
                $multiplier *= self::CONDITION_MULTIPLIER[$condition];
                $notes[] = 'Surcharge applied for: ' . str_replace('_', ' ', $condition) . '.';
            }
        }

        $marketLow = $this->cents($area * $this->rates->low($request->service) * $multiplier);
        $marketHigh = $this->cents($area * $this->rates->high($request->service) * $multiplier);
        $mid = $this->roundTo($this->cents($area * $this->rates->mid($request->service) * $multiplier), self::ROUND_TO_CENTS);

        $minimumApplied = $mid < $this->minimumCents;
        $total = max($mid, $this->minimumCents);
        if ($minimumApplied) {
            $notes[] = 'Small job — the ' . $this->plainMinimum() . ' minimum applies.';
        }

        return new Quote(
            service: $request->service,
            areaSqFt: $area,
            areaSource: $source,
            confidence: $confidence,
            marketLowCents: $marketLow,
            marketHighCents: $marketHigh,
            totalCents: $total,
            minimumCents: $this->minimumCents,
            minimumApplied: $minimumApplied,
            currency: $this->currency,
            notes: $notes,
        );
    }

    /**
     * Resolve wall area from the best signal available.
     *
     * @return array{0:int,1:string,2:string,3:string[]}  [area, source, confidence, notes]
     */
    private function area(QuoteRequest $request): array
    {
        $notes = [];

        if ($request->wallAreaSqFt !== null && $request->wallAreaSqFt > 0) {
            return [$request->wallAreaSqFt, 'measured', 'high', $notes];
        }

        if ($request->storeys !== null && $request->storeys > 0
            && $request->footprintSqFt !== null && $request->footprintSqFt > 0) {
            $perimeter = 4 * sqrt($request->footprintSqFt) * self::FOOTPRINT_SHAPE_FACTOR;
            $area = (int) round($perimeter * $request->storeys * self::WALL_HEIGHT_PER_STOREY_FT);
            $notes[] = "Wall area estimated from a {$request->footprintSqFt} sq ft footprint × {$request->storeys}-storey home.";

            return [$area, 'estimated from footprint', 'medium', $notes];
        }

        if ($request->sizeCategory !== null && isset(self::SIZE_WALL_AREA[$request->sizeCategory])) {
            $area = self::SIZE_WALL_AREA[$request->sizeCategory];
            $notes[] = "Wall area assumed for a '{$request->sizeCategory}' home — confirm on site for an exact price.";

            return [$area, 'estimated from size', 'low', $notes];
        }

        if ($request->photos !== []) {
            $area = self::SIZE_WALL_AREA[QuoteRequest::SIZE_MEDIUM];
            $notes[] = count($request->photos) . ' photo(s) received. Photos show the condition and layout but not exact size — '
                . 'this is a rough estimate to confirm with a measured or on-site check.';

            return [$area, 'estimated from size', 'low', $notes];
        }

        throw new \InvalidArgumentException(
            'Provide a wall area, storeys + footprint, a size category, or photos to estimate a quote.'
        );
    }

    /** Dollars (float) → integer cents. */
    private function cents(float $dollars): int
    {
        return (int) round($dollars * 100);
    }

    private function roundTo(int $cents, int $step): int
    {
        if ($step <= 0) {
            return $cents;
        }

        return (int) (round($cents / $step) * $step);
    }

    private function plainMinimum(): string
    {
        $whole = $this->minimumCents % 100 === 0
            ? number_format(intdiv($this->minimumCents, 100))
            : number_format($this->minimumCents / 100, 2);

        return '$' . $whole . ' ' . strtoupper($this->currency);
    }
}
