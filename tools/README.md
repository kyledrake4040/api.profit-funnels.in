# Price-floor calculator

Stops the "I lose money on every sale" leak by telling you the **minimum safe
selling price** for a product. It accounts for everything that eats a
dropshipping margin:

- supplier/product cost (e.g. Sellvia)
- shipping
- **ad cost per sale** (your CPA — usually the real culprit)
- payment-processing fees (percentage + fixed)
- your target profit margin

## The formula

```
net_profit(price) = price - (cost + shipping + ad + extra) - (price * fee% + fee_fixed)

floor_price = (cost + shipping + ad + extra + fee_fixed) / (1 - fee% - margin%)
```

If you sell below `floor_price`, you miss your margin. If you sell below the
**break-even price** (margin 0), you literally lose money on the sale.

## Run it (no install needed)

Plain PHP, no Composer or Laravel required:

```bash
# One product — also recommends a price, caps ad spend, projects monthly profit
php tools/price-floor.php --cost=12.50 --shipping=3.20 --ad=6.00 --margin=20 --price=24.99 --units=120

# A whole catalog — flags every product priced below its floor, worst first
php tools/price-floor.php --csv=tools/sample-products.csv --margin=20

# Write a corrected catalog (adds a suggested_price column) to re-import
php tools/price-floor.php --csv=tools/sample-products.csv --out=fixed.csv

# JSON instead of a table, for piping into other tools
php tools/price-floor.php --csv=tools/sample-products.csv --format=json
```

Defaults: `--fee-percent=2.9 --fee-fixed=0.30 --margin=20` (typical card fees).
The CSV header is `name,cost,shipping,ad,price` plus optional columns
`units` (monthly sales, for dollar-impact projections) and
`extra,fee_percent,fee_fixed,margin` (override the defaults per row).

The command exits with code `1` if any product is underpriced, so you can wire
it into a price sync or CI check.

### What you get back

- **Floor price** — the lowest price that still hits your target margin.
- **Suggested price** — the floor rounded up to a `.99` charm price, ready to paste into your store.
- **Max ad spend / sale** — the most you can pay per sale in ads and still hit margin (a negative value means the product is underpriced no matter what you do on ads).
- **Monthly impact** — with `units`, the extra profit per month you'd capture by applying the suggested prices.

## Inside the Laravel app

The same logic is exposed as an Artisan command and a reusable service
(`App\Services\PricingGuard`):

```bash
php artisan price:floor --cost=12.50 --shipping=3.20 --ad=6.00 --price=24.99
```

## Tests

```bash
php tools/price-floor-test.php
```
