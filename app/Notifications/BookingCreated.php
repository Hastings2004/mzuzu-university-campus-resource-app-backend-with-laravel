<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreated extends Notification implements ShouldQueue
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
            ->subject('Booking Created Successfully')
            ->view('emails.booking_created', [
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
            'title' => 'Booking Created Successfully',
            'message' => 'Your booking for ' . $this->booking->resource->name . ' has been created successfully.',
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'resource_name' => $this->booking->resource->name,
            'start_time' => $this->booking->start_time->toISOString(),
            'end_time' => $this->booking->end_time->toISOString(),
            'status' => $this->booking->status,
        ];
    }
}

class BookingCreatedAdmin extends Notification implements ShouldQueue
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
            ->subject('New Booking Created - Action Required')
            ->view('emails.booking_created_admin', [
                'booking' => $this->booking,
                'notifiable' => $notifiable,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Booking Created',
            'message' => 'A new booking for ' . $this->booking->resource->name . ' has been created and requires your attention.',
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'resource_name' => $this->booking->resource->name,
            'start_time' => $this->booking->start_time->toISOString(),
            'end_time' => $this->booking->end_time->toISOString(),
            'status' => $this->booking->status,
        ];
    }
} 