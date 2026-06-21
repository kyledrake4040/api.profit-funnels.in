<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Funnel\FunnelConfig;
use App\Funnel\Publishing\ApiPublisher;
use App\Funnel\Publishing\DryRunPublisher;
use App\Funnel\Publishing\PlatformPublisher;
use App\Funnel\Scheduler;
use App\Funnel\Storage\JsonVideoStore;
use Illuminate\Console\Command;

/**
 * `php artisan funnel:run`
 *
 * Laravel-native publisher: drains any due posts from the queue to every
 * configured platform. This is the scheduled entry point (see
 * routes/console.php) that replaces the bespoke deploy/run-loop.sh — it gains
 * the framework's locking (withoutOverlapping), logging and retries while
 * delegating to the same framework-free engine the CLI uses.
 *
 * Dormant until FUNNEL_ENABLED=true, so it is safe to schedule unconditionally.
 */
final class FunnelRunCommand extends Command
{
    protected $signature = 'funnel:run';

    protected $description = 'Publish any due funnel posts to the configured platforms';

    public function handle(FunnelConfig $config): int
    {
        if (! config('funnel.enabled')) {
            $this->info('Profit Funnel is disabled. Set FUNNEL_ENABLED=true to activate.');

            return self::SUCCESS;
        }

        $store = new JsonVideoStore($this->queuePath());
        $report = (new Scheduler($store, $this->publishers($config)))->run(time());

        if ($report === []) {
            $this->info('No posts are due right now.');

            return self::SUCCESS;
        }

        foreach ($report as $postId => $results) {
            $this->line("📤 {$postId}");
            foreach ($results as $platform => $result) {
                $icon = $result->success ? '✅' : '❌';
                $this->line("   {$icon} {$platform}: {$result->message}");
            }
        }

        return self::SUCCESS;
    }

    private function queuePath(): string
    {
        return (string) (config('funnel.queue_path') ?: storage_path('funnel/queue.json'));
    }

    /**
     * Social platforms (via GHL) plus Google Business Profile. Each goes live
     * once its endpoint + token are set; until then it dry-runs.
     *
     * @return PlatformPublisher[]
     */
    private function publishers(FunnelConfig $config): array
    {
        $list = [];
        foreach (array_merge($config->platforms, ['gbp']) as $platform) {
            $api = ApiPublisher::fromEnv($platform);
            $list[] = $api->isConnected() ? $api : new DryRunPublisher($platform);
        }

        return $list;
    }
}
