<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
Schedule::command('app:dispatch-scheduled-bookings')->everyMinute();
Schedule::command('paymongo:verify-pending')->everyFiveMinutes();
