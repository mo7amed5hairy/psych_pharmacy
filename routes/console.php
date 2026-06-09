<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('dump-users', function () {
    foreach (\App\Models\User::all() as $u) {
        $this->info($u->name . ' => ' . $u->employee_code);
    }
});

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;

Schedule::command('app:initialize-stock')->monthlyOn(1, '00:00');

// Heartbeat to monitor if scheduler is running
Schedule::call(function () {
    Cache::store('file')->put('scheduler_heartbeat', now()->toDateTimeString());
})->everyMinute();


