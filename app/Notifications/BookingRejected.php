<?php

// app/Notifications/BookingRejected.php
namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRejected extends Notification implements ShouldQueue
{
    use Queueable;
    protected $booking;
    protected $reason;
    protected $suggestions;

    public function __construct(Booking $booking, string $reason = 'Conflict with an existing booking.', array $suggestions = [])
    {
        $this->booking = $booking;
        $this->reason = $reason;
        $this->suggestions = $suggestions;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update on Your ' . $this->booking->resource->name . ' from Resource Booking Request')
            ->view('emails.booking_rejected', [
                'booking' => $this->booking,
                'reason' => $this->reason,
                'notifiable' => $notifiable,
                'suggestions' => $this->suggestions,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Booking Rejected',
            'message' => 'Your booking for ' . $this->booking->resource->name . ' was rejected. Reason: ' . $this->reason,
            'booking_id' => $this->booking->id,
            'resource_name' => $this->booking->resource->name,
            'start_time' => $this->booking->start_time->toDateTimeString(),
            'end_time' => $this->booking->end_time->toDateTimeString(),
            'status' => 'rejected',
            'suggestions' => $this->suggestions,
        ];
    }
}