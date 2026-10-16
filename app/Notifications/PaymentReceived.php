<?php
// app/Notifications/PaymentReceived.php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment)
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
        $booking = $this->payment->booking;

        return (new MailMessage)
            ->subject("Payment received - {$this->payment->receipt_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('We received your payment of ₱' . number_format((float) $this->payment->amount, 2) . " for booking #{$booking->id} ({$booking->event_name}).")
            ->line('Remaining balance: ₱' . number_format((float) $booking->balance, 2))
            ->action('View receipt', route('receipts.show', $this->payment->id))
            // The official receipt is attached as a real PDF.
            ->attachData(app(\App\Services\DocumentPdf::class)->receipt($this->payment), $this->payment->receipt_number . '.pdf', ['mime' => 'application/pdf'])
            ->line('Thank you!');
    }
}
