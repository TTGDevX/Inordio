<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily overdue-invoice reminders (runs when `php artisan schedule:work` is active).
Schedule::command('invoices:send-reminders')->dailyAt('08:00');

// Daily: spawn scheduled jobs for due service agreements.
Schedule::command('agreements:run')->dailyAt('06:00');
