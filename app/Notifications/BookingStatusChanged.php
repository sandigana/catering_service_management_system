<?php
// app/Notifications/BookingStatusChanged.php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    private const HEADLINES = [
        'confirmed' => ['Your booking is confirmed', 'Good news! Your booking has been confirmed.'],
        'cancelled' => ['Your booking was cancelled', 'Your booking has been cancelled. If this was a mistake, please contact us.'],
        'completed' => ['Thank you for choosing us', 'Your event is marked as completed. We hope everything was perfect!'],
        'pending' => ['Your booking is pending', 'Your booking is back to pending while we review it.'],
    ];

    public function __construct(public Booking $booking, public string $oldStatus)
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
        [$subject, $intro] = self::HEADLINES[$this->booking->status] ?? ['Your booking was updated', 'Your booking status changed.'];

        $mail = (new MailMessage)
            ->subject("{$subject} (#{$this->booking->id})")
            ->greeting("Hello {$notifiable->name},")
            ->line($intro);

        foreach ($this->booking->summaryLines() as $line) {
            $mail->line($line);
        }

        return $mail->action('View my booking', route('customer.bookings.show', $this->booking->id));
    }
}
