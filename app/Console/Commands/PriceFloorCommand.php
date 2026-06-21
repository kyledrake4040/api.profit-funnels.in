<?php

namespace App\Console\Commands;

use App\Services\PricingGuard;
use Illuminate\Console\Command;

class PriceFloorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:floor
        {--cost=0 : Supplier/product cost per unit}
        {--shipping=0 : Shipping cost per order}
        {--ad=0 : Ad cost per sale (your CPA)}
        {--extra=0 : Any other per-order cost}
        {--fee-percent=2.9 : Payment processing percentage fee}
        {--fee-fixed=0.30 : Payment processing fixed fee}
        {--margin=20 : Target net profit margin percent}
        {--price= : Current selling price to evaluate (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the minimum safe selling price (price floor) so a sale never loses money';

    /**
     * Execute the console command.
     *
     * @param  PricingGuard  $guard
     *
     * @return int
     */
    public function handle(PricingGuard $guard): int
    {
        $result = $guard->analyze([
            'cost' => $this->option('cost'),
            'shipping' => $this->option('shipping'),
            'ad' => $this->option('ad'),
            'extra' => $this->option('extra'),
            'fee_percent' => $this->option('fee-percent'),
            'fee_fixed' => $this->option('fee-fixed'),
            'margin' => $this->option('margin'),
            'price' => $this->option('price'),
        ]);

        if (!$result['feasible']) {
            $this->error('Target margin plus fees exceed 100% — no price can hit that margin. Lower the target margin or your costs.');

            return self::FAILURE;
        }

        $this->info(sprintf('Total cost per sale : %0.2f', $result['fixed_costs']));
        $this->info(sprintf('Break-even price    : %0.2f', $result['break_even_price']));
        $this->info(sprintf('Floor price (%d%%)    : %0.2f', (int) $result['target_margin_percent'], $result['floor_price']));

        if (!isset($result['current_price'])) {
            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Current price       : %0.2f | net %0.2f | margin %0.2f%%',
            $result['current_price'],
            $result['net_profit'],
            $result['net_margin_percent']
        ));

        if ($result['losing_money']) {
            $this->error(sprintf('LEAK: losing %0.2f on every sale. Raise price to at least %0.2f.', abs($result['net_profit']), $result['floor_price']));

            return self::FAILURE;
        }

        if ($result['below_floor']) {
            $this->warn(sprintf('UNDER FLOOR: %0.2f below target. Raise price to at least %0.2f.', $result['shortfall'], $result['floor_price']));

            return self::FAILURE;
        }

        $this->info('OK: price is at or above the floor.');

        return self::SUCCESS;
    }
}
