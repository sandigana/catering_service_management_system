<?php
// app/Notifications/NewBookingAlert.php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Back office heads-up: a new booking is waiting for approval. */
class NewBookingAlert extends Notification implements ShouldQueue
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
            ->subject("New booking request #{$this->booking->id}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A new booking is waiting for your decision:');

        foreach ($this->booking->summaryLines() as $line) {
            $mail->line($line);
        }

        return $mail->action('Review booking', route('admin.bookings.show', $this->booking->id));
    }
}
