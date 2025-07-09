<?php

namespace App\Notifications;

use App\Models\KeyTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class KeyOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transaction;

    /**
     * Create a new notification instance.
     */
    public function __construct(KeyTransaction $transaction)
    {
        $this->transaction = $transaction;
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
        $overdueHours = Carbon::now()->diffInHours($this->transaction->expected_return_at);
        
        return (new MailMessage)
            ->subject('URGENT: Your Key is Overdue - Action Required')
            ->view('emails.key_overdue', [
                'transaction' => $this->transaction,
                'notifiable' => $notifiable,
                'overdueHours' => $overdueHours,
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
            'title' => 'Key Overdue',
            'message' => 'Your key for ' . $this->transaction->key->resource->name . ' is overdue and needs to be returned immediately.',
            'transaction_id' => $this->transaction->id,
            'key_code' => $this->transaction->key->key_code,
            'resource_name' => $this->transaction->key->resource->name,
            'checked_out_at' => $this->transaction->checked_out_at->toISOString(),
            'expected_return_at' => $this->transaction->expected_return_at->toISOString(),
            'status' => $this->transaction->status,
        ];
    }
} 