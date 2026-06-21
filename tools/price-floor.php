<?php

/**
 * Standalone price-floor calculator.
 *
 * Tells you the minimum safe selling price for a product so you never lose
 * money on a sale, accounting for supplier cost, shipping, ad cost per sale,
 * payment-processing fees, and a target profit margin.
 *
 * Runs with plain PHP — no Composer/Laravel required:
 *
 *   Single product:
 *     php tools/price-floor.php --cost=10 --shipping=4 --ad=5 --margin=20 --price=24.99
 *
 *   A whole catalog from a CSV (columns: name,cost,shipping,ad,price):
 *     php tools/price-floor.php --csv=tools/sample-products.csv --margin=20
 *
 * Defaults: --fee-percent=2.9  --fee-fixed=0.30  --margin=20
 */

require __DIR__ . '/../app/Services/PricingGuard.php';

use App\Services\PricingGuard;

$options = getopt('', [
    'cost::', 'shipping::', 'ad::', 'extra::',
    'fee-percent::', 'fee-fixed::', 'margin::', 'price::',
    'csv::', 'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, helpText());
    exit(0);
}

$guard = new PricingGuard();

$defaults = [
    'fee_percent' => $options['fee-percent'] ?? 2.9,
    'fee_fixed' => $options['fee-fixed'] ?? 0.30,
    'margin' => $options['margin'] ?? 20,
];

$money = fn ($v) => $v === null ? '   n/a' : number_format((float) $v, 2);

if (isset($options['csv'])) {
    exit(runCsv($guard, $options['csv'], $defaults, $money));
}

// Single-product mode.
$result = $guard->analyze(array_merge($defaults, [
    'cost' => $options['cost'] ?? 0,
    'shipping' => $options['shipping'] ?? 0,
    'ad' => $options['ad'] ?? 0,
    'extra' => $options['extra'] ?? 0,
    'price' => $options['price'] ?? null,
]));

if (!$result['feasible']) {
    fwrite(STDERR, "Target margin plus fees exceed 100% — no price can hit that margin.\n"
        . "Lower the target margin or reduce your costs.\n");
    exit(2);
}

fwrite(STDOUT, "\nPrice-floor report\n");
fwrite(STDOUT, str_repeat('-', 48) . "\n");
fwrite(STDOUT, sprintf("Total cost per sale : %s\n", $money($result['fixed_costs'])));
fwrite(STDOUT, sprintf("Break-even price    : %s\n", $money($result['break_even_price'])));
fwrite(STDOUT, sprintf("Floor price (%2d%%)   : %s\n", (int) $result['target_margin_percent'], $money($result['floor_price'])));

if (isset($result['current_price'])) {
    fwrite(STDOUT, str_repeat('-', 48) . "\n");
    fwrite(STDOUT, sprintf("Current price       : %s\n", $money($result['current_price'])));
    fwrite(STDOUT, sprintf("Net profit / sale   : %s (%s%% margin)\n",
        $money($result['net_profit']), $money($result['net_margin_percent'])));

    if ($result['losing_money']) {
        fwrite(STDOUT, sprintf("LEAK: losing %s on every sale. Raise price to at least %s.\n",
            $money(abs($result['net_profit'])), $money($result['floor_price'])));
        exit(1);
    }

    if ($result['below_floor']) {
        fwrite(STDOUT, sprintf("UNDER FLOOR: %s below target. Raise price to at least %s.\n",
            $money($result['shortfall']), $money($result['floor_price'])));
        exit(1);
    }

    fwrite(STDOUT, "OK: price is at or above the floor.\n");
}

exit(0);

/**
 * Process a CSV catalog and flag every product priced below its floor.
 */
function runCsv(PricingGuard $guard, string $path, array $defaults, callable $money): int
{
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "CSV file not found or unreadable: {$path}\n");
        return 2;
    }

    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);

    if ($header === false) {
        fwrite(STDERR, "CSV is empty: {$path}\n");
        fclose($handle);
        return 2;
    }

    $columns = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

    fwrite(STDOUT, sprintf("\n%-24s %10s %10s %10s  %s\n", 'Product', 'Price', 'Floor', 'Net', 'Status'));
    fwrite(STDOUT, str_repeat('-', 70) . "\n");

    $leaks = 0;
    $total = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || count(array_filter($row, fn ($c) => $c !== null && $c !== '')) === 0) {
            continue;
        }

        $record = [];
        foreach ($columns as $i => $name) {
            $record[$name] = $row[$i] ?? null;
        }

        $input = array_merge($defaults, [
            'cost' => $record['cost'] ?? 0,
            'shipping' => $record['shipping'] ?? 0,
            'ad' => $record['ad'] ?? 0,
            'extra' => $record['extra'] ?? 0,
            'price' => $record['price'] ?? null,
        ]);

        // Allow per-row overrides of fee/margin when those columns exist.
        foreach (['fee_percent', 'fee_fixed', 'margin'] as $override) {
            if (isset($record[$override]) && $record[$override] !== '') {
                $input[$override] = $record[$override];
            }
        }

        $result = $guard->analyze($input);
        $total++;

        $name = (string) ($record['name'] ?? ('row ' . $total));
        $netDisplay = isset($result['net_profit']) ? $money($result['net_profit']) : '   n/a';
        $status = 'OK';

        if (!$result['feasible']) {
            $status = 'INFEASIBLE MARGIN';
            $leaks++;
        } elseif (isset($result['current_price'])) {
            if ($result['losing_money']) {
                $status = 'LEAK (losing money)';
                $leaks++;
            } elseif ($result['below_floor']) {
                $status = 'UNDER FLOOR';
                $leaks++;
            }
        } else {
            $status = 'no price set';
        }

        fwrite(STDOUT, sprintf(
            "%-24s %10s %10s %10s  %s\n",
            mb_strimwidth($name, 0, 24),
            isset($result['current_price']) ? $money($result['current_price']) : '   n/a',
            $money($result['floor_price']),
            $netDisplay,
            $status
        ));
    }

    fclose($handle);

    fwrite(STDOUT, str_repeat('-', 70) . "\n");
    fwrite(STDOUT, sprintf("%d product(s) checked, %d need a price change.\n", $total, $leaks));

    return $leaks > 0 ? 1 : 0;
}

function helpText(): string
{
    return <<<TXT

Price-floor calculator — find the minimum safe selling price.

Single product:
  php tools/price-floor.php --cost=10 --shipping=4 --ad=5 --margin=20 --price=24.99

CSV catalog (header: name,cost,shipping,ad,price):
  php tools/price-floor.php --csv=tools/sample-products.csv --margin=20

Options:
  --cost          Supplier/product cost per unit
  --shipping      Shipping cost per order
  --ad            Ad cost per sale (your CPA)
  --extra         Any other per-order cost
  --fee-percent   Payment processing percentage fee (default 2.9)
  --fee-fixed     Payment processing fixed fee (default 0.30)
  --margin        Target net profit margin percent (default 20)
  --price         Current selling price to evaluate (optional)
  --csv           Path to a CSV catalog instead of single-product flags
  --help          Show this help

Exit code is 1 when any product is priced below its floor, so this can gate a
deploy or a price sync in CI.

TXT;
}
