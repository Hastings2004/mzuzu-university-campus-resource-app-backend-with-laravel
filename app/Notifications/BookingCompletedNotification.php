<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $booking;

    /**
     * Create a new notification instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Resource Booking Has Been Completed')
            ->view('emails.booking_completed', [
                'booking' => $this->booking,
                'notifiable' => $notifiable,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Booking Completed',
            'message' => 'Your booking for ' . $this->booking->resource->name . ' has been completed.',
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'resource_name' => $this->booking->resource->name,
            'start_time' => $this->booking->start_time->toISOString(),
            'end_time' => $this->booking->end_time->toISOString(),
            'status' => $this->booking->status,
        ];
    }
} 