<?php
// app/Observers/BookingObserver.php

namespace App\Observers;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingAlert;

/** Sends the booking e-mails. A mail failure must never break saving a booking. */
class BookingObserver
{
    public function created(Booking $booking): void
    {
        $this->safely(function () use ($booking) {
            $booking->user?->notify(new BookingReceived($booking));

            // Tell the back office about requests that need a decision.
            if ($booking->status === 'pending') {
                User::where('is_active', true)->whereIn('role', ['admin', 'staff'])->get()
                    ->each(fn (User $staff) => $staff->notify(new NewBookingAlert($booking)));
            }
        });
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        $this->safely(fn () => $booking->user?->notify(
            new BookingStatusChanged($booking, (string) $booking->getOriginal('status'))
        ));
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
