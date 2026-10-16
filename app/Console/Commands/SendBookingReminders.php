<?php
// app/Console/Commands/SendBookingReminders.php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingReminder;
use App\Services\Sms\SmsGateway;
use Illuminate\Console\Command;

/**
 * php artisan bookings:send-reminders
 * E-mails (and optionally SMS) customers whose CONFIRMED event is
 * catering.reminder_days_before days away. Each booking is reminded only once.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Remind customers about their upcoming confirmed events';

    public function handle(SmsGateway $sms): int
    {
        $target = today()->addDays((int) config('catering.reminder_days_before'));

        $bookings = Booking::with('user')
            ->where('status', 'confirmed')
            ->whereDate('event_date', $target)
            ->whereNull('reminder_sent_at')
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            if (! $booking->user || ! $booking->user->is_active) {
                continue;
            }

            try {
                $booking->user->notify(new BookingReminder($booking));

                // Mark it as soon as the e-mail is out, so an SMS problem can never cause a second e-mail.
                // Quietly: marking a reminder as sent is not an audit-log event.
                $booking->updateQuietly(['reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                report($e);     // one failure must not stop everyone else's reminder

                continue;
            }

            if ($booking->user->phone) {
                try {
                    $sms->send($this->digits($booking->user->phone), $this->smsText($booking));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function smsText(Booking $booking): string
    {
        $when = $booking->event_date->format('M j') . ($booking->event_time ? ' ' . \Carbon\Carbon::parse($booking->event_time)->format('g:iA') : '');

        return mb_substr(config('app.name') . ": Reminder - {$booking->event_name} on {$when}"
            . ($booking->location ? " at {$booking->location}" : '') . '. See you there!', 0, 160);
    }

    private function digits(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}
