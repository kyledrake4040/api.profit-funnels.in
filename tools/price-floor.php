<?php

/**
 * Standalone price-floor calculator.
 *
 * Tells you the minimum safe selling price for a product so you never lose
 * money on a sale, recommends a ready-to-use price, caps your ad spend, and can
 * rewrite a whole catalog with corrected prices.
 *
 * Runs with plain PHP — no Composer/Laravel required:
 *
 *   Single product:
 *     php tools/price-floor.php --cost=10 --shipping=4 --ad=5 --margin=20 --price=24.99 --units=120
 *
 *   Scan a catalog (columns: name,cost,shipping,ad,price[,units]):
 *     php tools/price-floor.php --csv=tools/sample-products.csv --margin=20
 *
 *   Write a corrected catalog you can re-import to your store:
 *     php tools/price-floor.php --csv=tools/sample-products.csv --out=fixed.csv
 *
 *   JSON instead of a table:
 *     php tools/price-floor.php --csv=tools/sample-products.csv --format=json
 *
 * Defaults: --fee-percent=2.9  --fee-fixed=0.30  --margin=20
 */

require __DIR__ . '/../app/Services/PricingGuard.php';

use App\Services\PricingGuard;

$options = getopt('', [
    'cost::', 'shipping::', 'ad::', 'extra::', 'units::',
    'fee-percent::', 'fee-fixed::', 'margin::', 'price::',
    'csv::', 'out::', 'format::', 'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, helpText());
    exit(0);
}

$guard = new PricingGuard();
$format = $options['format'] ?? 'text';

$defaults = [
    'fee_percent' => $options['fee-percent'] ?? 2.9,
    'fee_fixed' => $options['fee-fixed'] ?? 0.30,
    'margin' => $options['margin'] ?? 20,
];

$money = fn ($v) => $v === null ? '   n/a' : number_format((float) $v, 2);

if (isset($options['csv'])) {
    $fallbacks = [
        'shipping' => $options['shipping'] ?? null,
        'ad' => $options['ad'] ?? null,
        'extra' => $options['extra'] ?? null,
        'units' => $options['units'] ?? null,
    ];
    exit(runCsv($guard, $options['csv'], $defaults, $fallbacks, $money, $options['out'] ?? null, $format));
}

// Single-product mode.
$result = $guard->analyze(array_merge($defaults, [
    'cost' => $options['cost'] ?? 0,
    'shipping' => $options['shipping'] ?? 0,
    'ad' => $options['ad'] ?? 0,
    'extra' => $options['extra'] ?? 0,
    'price' => $options['price'] ?? null,
    'units' => $options['units'] ?? null,
]));

if ($format === 'json') {
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . "\n");
    exit(isset($result['below_floor']) && $result['below_floor'] ? 1 : 0);
}

if (!$result['feasible']) {
    fwrite(STDERR, "Target margin plus fees exceed 100% — no price can hit that margin.\n"
        . "Lower the target margin or reduce your costs.\n");
    exit(2);
}

fwrite(STDOUT, "\nPrice-floor report\n");
fwrite(STDOUT, str_repeat('-', 52) . "\n");
fwrite(STDOUT, sprintf("Total cost per sale  : %s\n", $money($result['fixed_costs'])));
fwrite(STDOUT, sprintf("Break-even price     : %s\n", $money($result['break_even_price'])));
fwrite(STDOUT, sprintf("Floor price (%2d%%)    : %s\n", (int) $result['target_margin_percent'], $money($result['floor_price'])));
fwrite(STDOUT, sprintf("Suggested price      : %s  (nets %s/sale)\n", $money($result['suggested_price']), $money($result['net_at_suggested'])));

if (isset($result['current_price'])) {
    fwrite(STDOUT, str_repeat('-', 52) . "\n");
    fwrite(STDOUT, sprintf("Current price        : %s\n", $money($result['current_price'])));
    fwrite(STDOUT, sprintf("Net profit / sale    : %s (%s%% margin)\n",
        $money($result['net_profit']), $money($result['net_margin_percent'])));
    fwrite(STDOUT, sprintf("Max ad spend / sale  : %s  (your CPA must stay under this)\n", $money($result['max_ad_spend'])));

    if (isset($result['monthly_net_current'])) {
        fwrite(STDOUT, sprintf("Monthly net (now)    : %s over %d sales\n",
            $money($result['monthly_net_current']), (int) $result['units']));
        if (isset($result['monthly_gain'])) {
            fwrite(STDOUT, sprintf("Monthly gain if fixed: %s\n", $money($result['monthly_gain'])));
        }
    }

    if ($result['losing_money']) {
        fwrite(STDOUT, sprintf("\nLEAK: losing %s on every sale. Reprice to %s.\n",
            $money(abs($result['net_profit'])), $money($result['suggested_price'])));
        exit(1);
    }

    if ($result['below_floor']) {
        fwrite(STDOUT, sprintf("\nUNDER FLOOR: %s below target. Reprice to %s.\n",
            $money($result['shortfall']), $money($result['suggested_price'])));
        exit(1);
    }

    fwrite(STDOUT, "\nOK: price is at or above the floor.\n");
}

exit(0);

/**
 * Process a CSV catalog: flag underpriced products, optionally write a
 * corrected catalog, and report the total monthly dollar impact.
 */
function runCsv(PricingGuard $guard, string $path, array $defaults, array $fallbacks, callable $money, ?string $out, string $format): int
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

    // Resolve our canonical fields to a column index, accepting common aliases
    // (including Shopify product-export headers) so a native CSV and a raw
    // store export both work without any reformatting.
    $aliases = [
        'name' => ['name', 'title', 'product', 'handle'],
        'cost' => ['cost', 'cost per item', 'cost_per_item', 'item cost', 'unit cost'],
        'price' => ['price', 'variant price', 'selling price', 'sell price'],
        'shipping' => ['shipping', 'shipping cost', 'ship cost'],
        'ad' => ['ad', 'ad cost', 'ad_cost', 'cpa'],
        'units' => ['units', 'units sold', 'monthly units', 'qty sold', 'sold'],
        'extra' => ['extra', 'other cost'],
        'margin' => ['margin', 'target margin'],
        'fee_percent' => ['fee_percent', 'fee percent'],
        'fee_fixed' => ['fee_fixed', 'fee fixed'],
    ];

    $index = [];
    foreach ($aliases as $canonical => $names) {
        foreach ($names as $alias) {
            $pos = array_search($alias, $columns, true);
            if ($pos !== false) {
                $index[$canonical] = $pos;
                break;
            }
        }
    }

    $rows = [];
    $leaks = 0;
    $monthlyGain = 0.0;

    while (($line = fgetcsv($handle)) !== false) {
        if ($line === [null] || count(array_filter($line, fn ($c) => $c !== null && $c !== '')) === 0) {
            continue;
        }

        $val = function (string $field) use ($index, $line) {
            if (!isset($index[$field])) {
                return null;
            }
            $value = $line[$index[$field]] ?? null;

            return ($value === null || $value === '') ? null : $value;
        };

        // Skip Shopify variant rows that carry no price (e.g. image-only rows).
        if (isset($index['price']) && $val('price') === null && $val('cost') === null) {
            continue;
        }

        $input = array_merge($defaults, [
            'cost' => $val('cost') ?? 0,
            'shipping' => $val('shipping') ?? $fallbacks['shipping'] ?? 0,
            'ad' => $val('ad') ?? $fallbacks['ad'] ?? 0,
            'extra' => $val('extra') ?? $fallbacks['extra'] ?? 0,
            'price' => $val('price'),
            'units' => $val('units') ?? $fallbacks['units'],
        ]);

        foreach (['fee_percent', 'fee_fixed', 'margin'] as $override) {
            if ($val($override) !== null) {
                $input[$override] = $val($override);
            }
        }

        $result = $guard->analyze($input);
        $result['name'] = (string) ($val('name') ?? ('row ' . (count($rows) + 1)));
        $result['status'] = statusFor($result);

        if ($result['status'] !== 'OK' && $result['status'] !== 'no price set') {
            $leaks++;
        }
        if (isset($result['monthly_gain'])) {
            $monthlyGain += $result['monthly_gain'];
        }

        $rows[] = $result;
    }

    fclose($handle);

    // Worst leaks first.
    usort($rows, fn ($a, $b) => ($a['net_profit'] ?? INF) <=> ($b['net_profit'] ?? INF));

    if ($out !== null) {
        writeFixedCsv($out, $rows);
        fwrite(STDOUT, sprintf("Wrote corrected catalog to %s (suggested_price column added).\n", $out));
    }

    if ($format === 'json') {
        fwrite(STDOUT, json_encode($rows, JSON_PRETTY_PRINT) . "\n");
        return $leaks > 0 ? 1 : 0;
    }

    fwrite(STDOUT, sprintf("\n%-22s %9s %9s %9s %9s  %s\n", 'Product', 'Price', 'Floor', 'Suggest', 'Net', 'Status'));
    fwrite(STDOUT, str_repeat('-', 78) . "\n");

    foreach ($rows as $r) {
        fwrite(STDOUT, sprintf(
            "%-22s %9s %9s %9s %9s  %s\n",
            mb_strimwidth($r['name'], 0, 22),
            isset($r['current_price']) ? $money($r['current_price']) : '   n/a',
            $money($r['floor_price']),
            $money($r['suggested_price'] ?? null),
            isset($r['net_profit']) ? $money($r['net_profit']) : '   n/a',
            $r['status']
        ));
    }

    fwrite(STDOUT, str_repeat('-', 78) . "\n");
    fwrite(STDOUT, sprintf("%d product(s) checked, %d need a price change.\n", count($rows), $leaks));

    if (abs($monthlyGain) > 0.0) {
        fwrite(STDOUT, sprintf("Estimated extra profit per month if you apply the suggested prices: %s\n", $money($monthlyGain)));
    }

    return $leaks > 0 ? 1 : 0;
}

/**
 * Classify a product's pricing health.
 */
function statusFor(array $result): string
{
    if (!$result['feasible']) {
        return 'INFEASIBLE MARGIN';
    }
    if (!isset($result['current_price'])) {
        return 'no price set';
    }
    if ($result['losing_money']) {
        return 'LEAK (losing money)';
    }
    if ($result['below_floor']) {
        return 'UNDER FLOOR';
    }

    return 'OK';
}

/**
 * Write a corrected catalog with a suggested_price column.
 */
function writeFixedCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'w');
    fputcsv($handle, ['name', 'current_price', 'floor_price', 'suggested_price', 'net_at_suggested', 'status']);

    foreach ($rows as $r) {
        fputcsv($handle, [
            $r['name'],
            $r['current_price'] ?? '',
            $r['floor_price'] ?? '',
            $r['suggested_price'] ?? '',
            $r['net_at_suggested'] ?? '',
            $r['status'],
        ]);
    }

    fclose($handle);
}

function helpText(): string
{
    return <<<TXT

Price-floor calculator — find the minimum safe selling price and a recommended price.

Single product:
  php tools/price-floor.php --cost=10 --shipping=4 --ad=5 --margin=20 --price=24.99 --units=120

CSV catalog (header: name,cost,shipping,ad,price[,units]):
  php tools/price-floor.php --csv=tools/sample-products.csv --margin=20

Shopify export works directly (Title / Variant Price / Cost per item are
auto-detected); supply ad and shipping as flags since exports omit them:
  php tools/price-floor.php --csv=products_export.csv --shipping=3.50 --ad=6 --out=fixed.csv

Write a corrected catalog to re-import to your store:
  php tools/price-floor.php --csv=tools/sample-products.csv --out=fixed.csv

Options:
  --cost          Supplier/product cost per unit
  --shipping      Shipping cost per order
  --ad            Ad cost per sale (your CPA)
  --extra         Any other per-order cost
  --units         Units sold per month (for dollar-impact projections)
  --fee-percent   Payment processing percentage fee (default 2.9)
  --fee-fixed     Payment processing fixed fee (default 0.30)
  --margin        Target net profit margin percent (default 20)
  --price         Current selling price to evaluate (optional)
  --csv           Path to a CSV catalog instead of single-product flags
  --out           Write a corrected catalog (with suggested_price) to this path
  --format        text (default) or json
  --help          Show this help

Exit code is 1 when any product is priced below its floor, so this can gate a
deploy or a price sync in CI.

TXT;
}
