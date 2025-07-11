<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Your custom schedule for the MarkExpiredBookings command
//Schedule::command('bookings:mark-expired')->dailyAt('00:00'); // Example: run daily at midnight
// Or hourly:
Schedule::command('bookings:mark-expired')->hourly();

// Schedule the command to delete unverified users every hour
Schedule::command('users:delete-unverified --force')->hourly();

// Schedule the command to notify users about booking completion
Schedule::command('bookings:notify-completion')->everyFiveMinutes();

// Schedule the command to notify users about overdue keys
Schedule::command('keys:notify-overdue --force')->hourly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
