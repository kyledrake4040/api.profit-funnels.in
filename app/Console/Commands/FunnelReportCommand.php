<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Funnel\Attribution\AttributionReport;
use App\Funnel\Attribution\AttributionStore;
use Illuminate\Console\Command;

/**
 * `php artisan funnel:report --days=7`
 *
 * Laravel-native entry point for the attribution report. Receives the
 * configured AttributionStore via dependency injection (the same binding the
 * webhooks and dashboard use), so it reads the production database-backed store
 * rather than the CLI's JSON file when funnel.attribution_driver=eloquent.
 */
final class FunnelReportCommand extends Command
{
    protected $signature = 'funnel:report {--days=7 : Reporting window in days}';

    protected $description = 'Show funnel attribution: leads & revenue, funnel vs. other';

    public function handle(AttributionStore $store): int
    {
        $days = max(1, (int) $this->option('days'));

        $this->output->write((new AttributionReport($store))->render($days));

        return self::SUCCESS;
    }
}
