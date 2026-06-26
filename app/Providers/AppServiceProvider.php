<?php

namespace App\Providers;

use App\Ai\ClaudeClient;
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
        // Funnel attribution wiring lives in FunnelServiceProvider.
        Schema::defaultStringLength(191);

        // Claude client for AI features — built from config, swappable in tests.
        $this->app->singleton(ClaudeClient::class, fn () => new ClaudeClient(
            (string) config('services.claude.key', ''),
            (string) config('services.claude.model', 'claude-opus-4-8'),
        ));
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
