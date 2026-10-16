<?php
// app/Notifications/PaymentProofSubmitted.php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Back office: a customer uploaded a payment slip to verify. */
class PaymentProofSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment)
    {
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
            ->subject("Payment proof to verify - booking #{$booking->id}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$booking->user->name} sent proof of a PHP " . number_format((float) $this->payment->amount, 2)
                . ' payment' . ($this->payment->payment_method ? " via {$this->payment->payment_method}" : '') . '.')
            ->line($this->payment->reference_number ? 'Reference: ' . $this->payment->reference_number : 'No reference number given.')
            ->action('Verify payment', route('admin.payments.show', $this->payment->id));
    }
}
