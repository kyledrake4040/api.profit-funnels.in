<?php

namespace App\Services;

/**
 * PricingGuard computes the minimum safe selling price for a product so that a
 * sale never loses money, and recommends a ready-to-use price. It accounts for
 * supplier cost, shipping, ad cost per sale (CPA), payment-processing fees
 * (percentage + fixed), and a target net profit margin.
 *
 * The price floor is derived from:
 *
 *   net_profit(P) = P - fixed_costs - (P * fee_pct + fee_fixed)
 *
 * Requiring net_profit(P) >= margin * P and solving for P gives:
 *
 *   P_floor = (fixed_costs + fee_fixed) / (1 - fee_pct - margin)
 *
 * This class has no framework dependencies so it can run standalone or inside
 * Laravel.
 */
class PricingGuard
{
    /**
     * Analyze a single product's economics.
     *
     * Accepted input keys: cost, shipping, ad, extra, fee_percent, fee_fixed,
     * margin (target %), and optionally price (current selling price) and units
     * (units sold per month, for dollar-impact projections).
     *
     * @param  array  $input
     *
     * @return array
     */
    public function analyze(array $input): array
    {
        $cost = $this->toFloat($input['cost'] ?? 0);
        $shipping = $this->toFloat($input['shipping'] ?? 0);
        $ad = $this->toFloat($input['ad'] ?? 0);
        $extra = $this->toFloat($input['extra'] ?? 0);
        $feePercent = $this->toFloat($input['fee_percent'] ?? 0);
        $feeFixed = $this->toFloat($input['fee_fixed'] ?? 0);
        $margin = $this->toFloat($input['margin'] ?? 0);

        $f = $feePercent / 100;
        $m = $margin / 100;
        $fixedCosts = $cost + $shipping + $ad + $extra;

        $breakEven = $this->solvePrice($fixedCosts, $feeFixed, $f, 0.0);
        $floor = $this->solvePrice($fixedCosts, $feeFixed, $f, $m);

        $result = [
            'fixed_costs' => round($fixedCosts, 2),
            'break_even_price' => $breakEven === null ? null : round($breakEven, 2),
            'floor_price' => $floor === null ? null : round($floor, 2),
            'target_margin_percent' => $margin,
            'feasible' => $floor !== null,
        ];

        // A ready-to-use recommended price: the floor rounded up to a .99 charm price.
        if ($floor !== null) {
            $suggested = $this->suggestedPrice($floor);
            $result['suggested_price'] = $suggested;
            $result['net_at_suggested'] = round($this->netProfit($suggested, $fixedCosts, $feeFixed, $f), 2);
        }

        $hasPrice = isset($input['price']) && $input['price'] !== null && $input['price'] !== '';

        if ($hasPrice) {
            $price = $this->toFloat($input['price']);
            $net = $this->netProfit($price, $fixedCosts, $feeFixed, $f);

            $result['current_price'] = round($price, 2);
            $result['net_profit'] = round($net, 2);
            $result['net_margin_percent'] = $price > 0 ? round($net / $price * 100, 2) : null;
            $result['below_floor'] = $floor !== null && $price < $floor;
            $result['losing_money'] = $net < 0;
            $result['shortfall'] = ($floor !== null && $price < $floor) ? round($floor - $price, 2) : 0.0;

            // The most you can afford to pay per sale in ads at this price and
            // still hit the target margin. Negative means even free ads miss it.
            $result['max_ad_spend'] = round($price * (1 - $f - $m) - $feeFixed - ($fixedCosts - $ad), 2);
        }

        // Dollar impact when monthly units are supplied.
        if (isset($input['units']) && $input['units'] !== null && $input['units'] !== '') {
            $units = $this->toFloat($input['units']);
            $result['units'] = $units;

            if ($hasPrice) {
                $result['monthly_net_current'] = round($result['net_profit'] * $units, 2);
            }

            if (isset($result['net_at_suggested'])) {
                $result['monthly_net_at_suggested'] = round($result['net_at_suggested'] * $units, 2);
            }

            if ($hasPrice && isset($result['net_at_suggested'])) {
                $result['monthly_gain'] = round(($result['net_at_suggested'] - $result['net_profit']) * $units, 2);
            }
        }

        return $result;
    }

    /**
     * Solve for the price that achieves the given target margin.
     *
     * Returns null when fees plus target margin reach or exceed 100% of price,
     * which makes the target mathematically impossible.
     *
     * @param  float  $fixedCosts
     * @param  float  $feeFixed
     * @param  float  $f  payment fee as a fraction
     * @param  float  $m  target margin as a fraction
     *
     * @return float|null
     */
    private function solvePrice(float $fixedCosts, float $feeFixed, float $f, float $m): ?float
    {
        $denominator = 1 - $f - $m;

        if ($denominator <= 0) {
            return null;
        }

        return ($fixedCosts + $feeFixed) / $denominator;
    }

    /**
     * Net profit at a given selling price.
     *
     * @param  float  $price
     * @param  float  $fixedCosts
     * @param  float  $feeFixed
     * @param  float  $f
     *
     * @return float
     */
    private function netProfit(float $price, float $fixedCosts, float $feeFixed, float $f): float
    {
        $fee = $price * $f + $feeFixed;

        return $price - $fixedCosts - $fee;
    }

    /**
     * Round a floor price up to the next ".99" charm price at or above it.
     *
     * @param  float  $floor
     *
     * @return float
     */
    private function suggestedPrice(float $floor): float
    {
        $candidate = ceil($floor) - 0.01;

        if ($candidate < $floor - 1e-9) {
            $candidate = ceil($floor) + 0.99;
        }

        return round($candidate, 2);
    }

    /**
     * Coerce a value (string from CLI/CSV, or numeric) to float.
     *
     * @param  mixed  $value
     *
     * @return float
     */
    private function toFloat($value): float
    {
        if (is_string($value)) {
            $value = trim(str_replace([',', '$'], '', $value));
        }

        return (float) $value;
    }
}
