<?php

declare(strict_types=1);

namespace App\Providers;

use App\Funnel\Attribution\AttributionStore;
use App\Funnel\Attribution\EloquentAttributionStore;
use App\Funnel\Attribution\JsonAttributionStore;
use App\Funnel\FunnelConfig;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the funnel module into the container. Keeping this binding in its own
 * provider (rather than AppServiceProvider) keeps the module boundary explicit:
 * anything that needs attribution persistence type-hints {@see AttributionStore}
 * and receives the driver configured by funnel.attribution_driver.
 */
final class FunnelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The engine config is read once from the environment per process.
        $this->app->singleton(FunnelConfig::class, static fn (): FunnelConfig => FunnelConfig::fromEnv());

        $this->app->bind(AttributionStore::class, function (): AttributionStore {
            if (config('funnel.attribution_driver') === 'eloquent') {
                return new EloquentAttributionStore();
            }

            $path = config('funnel.json_store_path') ?: base_path('storage/funnel/attribution.json');

            return new JsonAttributionStore($path);
        });
    }
}
