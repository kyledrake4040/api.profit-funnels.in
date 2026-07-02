<?php

declare(strict_types=1);

namespace App\Funnel\Quoting;

/**
 * The inputs for a job quote.
 *
 * Area can be supplied four ways, most-accurate first:
 *   1. $wallAreaSqFt      — measured wall area (exact price)
 *   2. $storeys + $footprintSqFt — estimated from the home's footprint
 *   3. $sizeCategory      — a rough small/medium/large bucket
 *   4. $photos            — one shot per side; shows condition, not exact size,
 *                           so it yields a clearly-flagged rough estimate.
 */
final class QuoteRequest
{
    public const SERVICE_SOFT_WASH = 'soft_wash';
    public const SERVICE_POWER_WASH = 'power_wash';
    public const SERVICE_COMBO = 'combo';
    public const SERVICE_PAINTING = 'painting';

    public const SIZE_SMALL = 'small';
    public const SIZE_MEDIUM = 'medium';
    public const SIZE_LARGE = 'large';

    /**
     * @param string      $service       one of the SERVICE_* constants
     * @param int|null    $wallAreaSqFt  exact wall area in sq ft, if known
     * @param int|null    $storeys       number of storeys (for a footprint estimate)
     * @param int|null    $footprintSqFt ground-floor footprint (for a footprint estimate)
     * @param string|null $sizeCategory  a SIZE_* bucket when nothing is measured
     * @param string[]    $photos        paths to one photo per side of the home
     * @param string[]    $conditions    difficulty flags — see QuoteEstimator::CONDITION_MULTIPLIER
     * @param string|null $customerName  for the written quote
     * @param string|null $address       for the written quote
     */
    public function __construct(
        public readonly string $service,
        public readonly ?int $wallAreaSqFt = null,
        public readonly ?int $storeys = null,
        public readonly ?int $footprintSqFt = null,
        public readonly ?string $sizeCategory = null,
        public readonly array $photos = [],
        public readonly array $conditions = [],
        public readonly ?string $customerName = null,
        public readonly ?string $address = null,
    ) {
    }
}
