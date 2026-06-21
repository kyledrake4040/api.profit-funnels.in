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
# One product
php tools/price-floor.php --cost=12.50 --shipping=3.20 --ad=6.00 --margin=20 --price=24.99

# A whole catalog — flags every product priced below its floor
php tools/price-floor.php --csv=tools/sample-products.csv --margin=20
```

Defaults: `--fee-percent=2.9 --fee-fixed=0.30 --margin=20` (typical card fees).
The CSV header must be `name,cost,shipping,ad,price` (optional extra columns:
`extra,fee_percent,fee_fixed,margin` override the defaults per row).

The command exits with code `1` if any product is underpriced, so you can wire
it into a price sync or CI check.

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
