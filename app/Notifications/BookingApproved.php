<?php

// app/Notifications/BookingApproved.php
namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingApproved extends Notification implements ShouldQueue
{
    use Queueable;
    protected $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Resource Booking is Approved! - Mzuzu University Campus Resource Booking System')
            ->view('emails.booking_approved', [
                'booking' => $this->booking,
                'notifiable' => $notifiable,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Booking Approved',
            'message' => 'Your booking for ' . $this->booking->resource->name . ' has been approved.',
            'booking_id' => $this->booking->id,
            'resource_name' => $this->booking->resource->name,
            'start_time' => $this->booking->start_time->toDateTimeString(),
            'end_time' => $this->booking->end_time->toDateTimeString(),
            'status' => 'approved',
        ];
    }
}