<?php

declare(strict_types=1);

namespace App\Funnel\Quoting;

/**
 * Surveyed competitor price bands, in dollars per square foot of wall area.
 *
 * The quote engine deliberately charges the MIDPOINT of each band — not the
 * cheapest, not the dearest — so Gulf Coast lands in the trustworthy middle of
 * the local market. The defaults are anchored to published 2026 wash pricing
 * ($0.20–$0.55/sq ft range); tune them with 2–3 real local quotes, or override
 * any value via env (e.g. QUOTE_COMBO_LOW=0.50 QUOTE_COMBO_HIGH=0.80).
 */
final class MarketRates
{
    /** @param array<string,array{0:float,1:float}> $bands service => [low $/sqft, high $/sqft] */
    public function __construct(private readonly array $bands)
    {
    }

    public static function defaults(): self
    {
        return new self([
            QuoteRequest::SERVICE_SOFT_WASH => [0.35, 0.60],
            QuoteRequest::SERVICE_POWER_WASH => [0.25, 0.50],
            QuoteRequest::SERVICE_COMBO => [0.45, 0.75],
            QuoteRequest::SERVICE_PAINTING => [2.50, 4.50],
        ]);
    }

    /** Same bands, with each low/high overridable via QUOTE_<SERVICE>_LOW / _HIGH. */
    public static function fromEnv(): self
    {
        $bands = [];
        foreach (self::defaults()->bands as $service => [$low, $high]) {
            $key = 'QUOTE_' . strtoupper($service);
            $bands[$service] = [
                self::envFloat($key . '_LOW', $low),
                self::envFloat($key . '_HIGH', $high),
            ];
        }

        return new self($bands);
    }

    public function has(string $service): bool
    {
        return isset($this->bands[$service]);
    }

    public function low(string $service): float
    {
        return $this->band($service)[0];
    }

    public function high(string $service): float
    {
        return $this->band($service)[1];
    }

    /** The price we actually quote: the middle of the market. */
    public function mid(string $service): float
    {
        [$low, $high] = $this->band($service);

        return ($low + $high) / 2;
    }

    /** @return array{0:float,1:float} */
    public function band(string $service): array
    {
        if (! isset($this->bands[$service])) {
            throw new \InvalidArgumentException("Unknown service \"{$service}\".");
        }

        return $this->bands[$service];
    }

    private static function envFloat(string $key, float $default): float
    {
        $value = getenv($key);

        return $value !== false && is_numeric($value) ? (float) $value : $default;
    }
}
