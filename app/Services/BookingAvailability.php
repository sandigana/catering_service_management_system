<?php
// app/Services/BookingAvailability.php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Decides whether a date/time/venue can still be booked.
 * Two rules (both configurable in config/catering.php):
 *   1. Daily capacity: no more than max_events_per_day active bookings per date.
 *   2. Venue clash: same venue on the same date within venue_slot_hours of each other.
 * Call inside a DB transaction so the row lock prevents two simultaneous requests
 * from both grabbing the last slot.
 */
class BookingAvailability
{
    /** Returns a human-readable reason, or null when the slot is free. */
    public function conflict(string $date, ?string $time, ?string $venue, ?int $ignoreId = null, bool $lock = true): ?string
    {
        $sameDay = Booking::query()
            ->whereDate('event_date', $date)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'event_time', 'location']);

        if ($sameDay->count() >= config('catering.max_events_per_day')) {
            return 'That date is fully booked. Please choose another date.';
        }

        if (! $venue) {
            return null;
        }

        $wanted = $this->normalise($venue);

        foreach ($sameDay as $other) {
            if ($this->normalise((string) $other->location) !== $wanted) {
                continue;
            }

            if ($this->timesOverlap($time, $other->event_time)) {
                return 'That venue is already booked around that time on this date.';
            }
        }

        return null;
    }

    /** Throws a validation error on event_date when the slot is taken. */
    public function assertAvailable(string $date, ?string $time, ?string $venue, ?int $ignoreId = null): void
    {
        if ($message = $this->conflict($date, $time, $venue, $ignoreId)) {
            throw ValidationException::withMessages(['event_date' => $message]);
        }
    }

    private function normalise(string $venue): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $venue)));
    }

    /** Unknown time on either side is treated as a clash (safer than a double booking). */
    private function timesOverlap(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return true;
        }

        $first = Carbon::createFromFormat('H:i', substr($a, 0, 5));
        $second = Carbon::createFromFormat('H:i', substr($b, 0, 5));

        return abs($first->diffInMinutes($second, false)) < config('catering.venue_slot_hours') * 60;
    }
}
