<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule ephemeral data purging every hour (01-context.md & 05-infrastructure.md)
Schedule::command('emiringkay:purge-ephemeral')->hourly();
