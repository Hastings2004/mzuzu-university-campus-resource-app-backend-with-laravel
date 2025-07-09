<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Notifications\BookingWillCompleteSoon;
use App\Notifications\BookingCompletedNotification;
use Carbon\Carbon;

class NotifyBookingCompletion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:notify-completion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users by email when their booking will be completed in 10 minutes and when it is completed.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = Carbon::now();
        $tenMinutesLater = $now->copy()->addMinutes(10);

        // Notify users whose bookings will end in 10 minutes (and haven't been notified yet)
        $soonBookings = Booking::where('end_time', '>=', $now)
            ->where('end_time', '<', $tenMinutesLater)
            ->whereIn('status', [Booking::STATUS_APPROVED, Booking::STATUS_IN_USE])
            ->whereNull('will_complete_notified_at')
            ->get();

        foreach ($soonBookings as $booking) {
            $booking->user->notify(new BookingWillCompleteSoon($booking));
            $booking->will_complete_notified_at = $now;
            $booking->save();
            $this->info("Notified user {$booking->user->email} that booking #{$booking->id} will complete in 10 minutes.");
        }

        // Notify users whose bookings have just been completed (and haven't been notified yet)
        $completedBookings = Booking::where('end_time', '<=', $now)
            ->whereIn('status', [Booking::STATUS_COMPLETED])
            ->whereNull('completed_notified_at')
            ->get();

        foreach ($completedBookings as $booking) {
            $booking->user->notify(new BookingCompletedNotification($booking));
            $booking->completed_notified_at = $now;
            $booking->save();
            $this->info("Notified user {$booking->user->email} that booking #{$booking->id} is completed.");
        }

        $this->info('Booking notification process completed.');
        return 0;
    }
} 