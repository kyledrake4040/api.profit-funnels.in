<?php

namespace App\Providers;

use App\Funnel\Attribution\AttributionStore;
use App\Funnel\Attribution\EloquentAttributionStore;
use App\Funnel\Attribution\JsonAttributionStore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
		Schema::defaultStringLength(191);

        $this->app->bind(AttributionStore::class, function (): AttributionStore {
            if (config('funnel.attribution_driver') === 'eloquent') {
                return new EloquentAttributionStore();
            }

            return new JsonAttributionStore(base_path('storage/funnel/attribution.json'));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
