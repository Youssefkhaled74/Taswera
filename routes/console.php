<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sync-jobs:send-pending-failed')
        ->everyTenMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/sync-jobs.log'));
