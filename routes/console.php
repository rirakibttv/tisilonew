<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --stop-when-empty --queue=integrations,default --tries=5 --timeout=55 --max-jobs=100 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('shipping:sync-statuses --limit=100')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('meta-catalog:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('sitemap:sync')
    ->hourly()
    ->withoutOverlapping();
