<?php
// app/Notifications/RescheduleRequested.php

namespace App\Notifications;

use App\Models\RescheduleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Back office: a customer asked to move their event. */
class RescheduleRequested extends Notification implements ShouldQueue
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
        $new = $this->request->requested_date->format('F j, Y')
            . ($this->request->requested_time ? ' at ' . \Carbon\Carbon::parse($this->request->requested_time)->format('g:i A') : '');

        return (new MailMessage)
            ->subject("Reschedule request for booking #{$booking->id}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$booking->user->name} asked to move \"{$booking->event_name}\".")
            ->line('Current date: ' . $booking->event_date->format('F j, Y'))
            ->line("Requested: {$new}")
            ->line($this->request->reason ? 'Reason: ' . $this->request->reason : 'No reason given.')
            ->action('Review request', route('admin.reschedules.index'));
    }
}
