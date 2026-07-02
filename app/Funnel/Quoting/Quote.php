<?php

declare(strict_types=1);

namespace App\Funnel\Quoting;

/**
 * A priced quote: the mid-market total plus the surrounding market band, so the
 * customer can see the price sits fairly in the middle of what PEI charges.
 *
 * All money is in integer cents to avoid floating-point drift.
 */
final class Quote
{
    /**
     * @param string   $areaSource 'measured' | 'estimated from footprint' | 'estimated from size'
     * @param string   $confidence 'high' | 'medium' | 'low'
     * @param string[] $notes      assumptions / caveats to show the customer or the estimator
     */
    public function __construct(
        public readonly string $service,
        public readonly int $areaSqFt,
        public readonly string $areaSource,
        public readonly string $confidence,
        public readonly int $marketLowCents,
        public readonly int $marketHighCents,
        public readonly int $totalCents,
        public readonly int $minimumCents,
        public readonly bool $minimumApplied,
        public readonly string $currency,
        public readonly array $notes = [],
    ) {
    }

    /** e.g. "$1,200 CAD" */
    public function amount(int $cents): string
    {
        $whole = $cents % 100 === 0
            ? number_format(intdiv($cents, 100))
            : number_format($cents / 100, 2);

        return '$' . $whole . ' ' . strtoupper($this->currency);
    }

    /** "Comparable PEI jobs run $900–$1,500 CAD" */
    public function marketRangeLabel(): string
    {
        return $this->amount($this->marketLowCents) . '–' . $this->amount($this->marketHighCents);
    }
}
