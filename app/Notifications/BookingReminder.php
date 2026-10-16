<?php
// app/Notifications/BookingReminder.php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Customer: your event is coming up. */
class BookingReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = max(0, (int) round(today()->diffInDays($this->booking->event_date, false)));

        $mail = (new MailMessage)
            ->subject('Reminder: your event ' . ($days === 1 ? 'is tomorrow' : ($days === 0 ? 'is today' : "is in {$days} days")))
            ->greeting("Hello {$notifiable->name},")
            ->line('Just a friendly reminder about your upcoming event:');

        foreach ($this->booking->summaryLines() as $line) {
            $mail->line($line);
        }

        if ((float) $this->booking->balance > 0) {
            $mail->line('Please remember the remaining balance of PHP ' . number_format((float) $this->booking->balance, 2) . '.');
        }

        return $mail->action('View my booking', route('customer.bookings.show', $this->booking->id));
    }
}
