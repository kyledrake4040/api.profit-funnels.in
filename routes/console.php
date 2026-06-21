<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drain the funnel queue every 5 minutes. The command is dormant unless
// FUNNEL_ENABLED=true, so scheduling it unconditionally is safe.
Schedule::command('funnel:run')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('funnel/run.log'));

// Weekly attribution report (Mondays 08:00) -> report.log.
Schedule::command('funnel:report --days=7')
    ->weeklyOn(1, '08:00')
    ->appendOutputTo(storage_path('funnel/report.log'));
