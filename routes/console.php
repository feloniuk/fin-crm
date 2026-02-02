<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Here you can define the scheduled commands for your application.
| To activate the scheduler, add this cron entry on your server:
|
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Sync orders based on individual shop intervals (checks every 5 minutes)
Schedule::command('orders:sync')->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Check company limits daily at 08:00
Schedule::command('limits:check')->dailyAt('08:00')
    ->withoutOverlapping();
