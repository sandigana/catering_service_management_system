<?php
// app/Notifications/RescheduleDecided.php

namespace App\Notifications;

use App\Models\RescheduleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Customer: the back office approved or rejected the date change. */
class RescheduleDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RescheduleRequest $request)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->request->booking;
        $approved = $this->request->status === 'approved';

        $mail = (new MailMessage)
            ->subject(($approved ? 'Your new date is confirmed' : 'Reschedule request declined') . " (#{$booking->id})")
            ->greeting("Hello {$notifiable->name},");

        if ($approved) {
            $mail->line('Good news! We moved your event to the new date.');
            foreach ($booking->summaryLines() as $line) {
                $mail->line($line);
            }
        } else {
            $mail->line("Sorry, we couldn't move \"{$booking->event_name}\" to the date you asked for. Your original date still stands.");
            if ($this->request->admin_note) {
                $mail->line('Note from us: ' . $this->request->admin_note);
            }
        }

        return $mail->action('View my booking', route('customer.bookings.show', $booking->id));
    }
}
