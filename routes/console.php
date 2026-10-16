<?php
// routes/console.php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly database backup (needs the scheduler cron entry, see README).
Schedule::command('db:backup')->dailyAt('02:00')->onOneServer();

// Morning jobs: customer reminders and the low-stock list for the back office.
Schedule::command('bookings:send-reminders')->dailyAt('08:00')->onOneServer();
Schedule::command('inventory:low-stock-digest')->dailyAt('08:05')->onOneServer();
