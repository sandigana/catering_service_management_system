<?php
// app/Notifications/BookingReceived.php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        // Mail goes out only after the surrounding DB transaction commits.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("We received your booking #{$this->booking->id}")
            ->greeting("Hello {$notifiable->name},")
            ->line('Thank you! We received your booking request:');

        foreach ($this->booking->summaryLines() as $line) {
            $mail->line($line);
        }

        return $mail
            ->line('We will review it and e-mail you as soon as it is confirmed.')
            ->action('View my booking', route('customer.bookings.show', $this->booking->id));
    }
}
