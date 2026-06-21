<?php

/**
 * Self-contained assertions for PricingGuard. Runs with plain PHP:
 *
 *   php tools/price-floor-test.php
 *
 * Exits non-zero on the first failed assertion.
 */

require __DIR__ . '/../app/Services/PricingGuard.php';

use App\Services\PricingGuard;

$failures = 0;

function approx($actual, $expected, string $label, float $tolerance = 0.01): void
{
    global $failures;
    if ($actual === null) {
        fwrite(STDERR, "FAIL: {$label} — got null, expected {$expected}\n");
        $failures++;
        return;
    }
    if (abs($actual - $expected) > $tolerance) {
        fwrite(STDERR, sprintf("FAIL: %s — got %.4f, expected %.4f\n", $label, $actual, $expected));
        $failures++;
        return;
    }
    fwrite(STDOUT, sprintf("ok: %s (%.2f)\n", $label, $actual));
}

function assertTrue($cond, string $label): void
{
    global $failures;
    if ($cond) {
        fwrite(STDOUT, "ok: {$label}\n");
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $failures++;
    }
}

$guard = new PricingGuard();

// cost 10 + shipping 4 + ad 5 = 19 fixed; fee 2.9% + 0.30; target margin 20%.
$r = $guard->analyze([
    'cost' => 10, 'shipping' => 4, 'ad' => 5,
    'fee_percent' => 2.9, 'fee_fixed' => 0.30, 'margin' => 20,
    'price' => 24.99,
]);

approx($r['fixed_costs'], 19.00, 'fixed costs');
approx($r['break_even_price'], 19.88, 'break-even price'); // 19.30 / 0.971
approx($r['floor_price'], 25.03, 'floor price');           // 19.30 / 0.771
assertTrue($r['feasible'] === true, 'feasible at 20% margin');
assertTrue($r['below_floor'] === true, '24.99 is below the 25.03 floor');
assertTrue($r['losing_money'] === false, '24.99 still nets a profit');
approx($r['net_profit'], 4.97, 'net profit at 24.99');
approx($r['shortfall'], 0.04, 'shortfall vs floor');

// A clearly money-losing price.
$loss = $guard->analyze([
    'cost' => 12.50, 'shipping' => 3.20, 'ad' => 6.00,
    'fee_percent' => 2.9, 'fee_fixed' => 0.30, 'margin' => 20,
    'price' => 14.99,
]);
assertTrue($loss['losing_money'] === true, 'underpriced product flagged as losing money');
assertTrue($loss['net_profit'] < 0, 'net profit is negative on the loss product');

// Impossible target: fee + margin >= 100%.
$infeasible = $guard->analyze([
    'cost' => 5, 'fee_percent' => 10, 'margin' => 95,
]);
assertTrue($infeasible['feasible'] === false, 'fee + 95% margin is infeasible');
assertTrue($infeasible['floor_price'] === null, 'no floor price when infeasible');

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll PricingGuard assertions passed.\n");
exit(0);
