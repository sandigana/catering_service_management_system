<?php
// app/Observers/PaymentObserver.php

namespace App\Observers;

use App\Models\Payment;
use App\Notifications\PaymentReceived;

/** E-mails the customer when money is actually received (status = paid). */
class PaymentObserver
{
    public function created(Payment $payment): void
    {
        if ($payment->status === 'paid') {
            $this->notify($payment);
        }
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === 'paid') {
            $this->notify($payment);
        }
    }

    private function notify(Payment $payment): void
    {
        try {
            $payment->user?->notify(new PaymentReceived($payment));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
