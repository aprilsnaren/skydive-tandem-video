<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete old uploads and exports every day at 02:00
Schedule::command('videoedit:prune')->dailyAt('02:00');

// Send reminder/expiry notification emails every day at 09:00
Schedule::command('videoedit:notify')->dailyAt('09:00');
