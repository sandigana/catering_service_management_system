<?php
// app/Http/Controllers/AvailabilityController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Lets the booking form tell the customer, before they submit, whether a date
 * (and optionally a time + venue) is still free. Uses the same rules as the real
 * booking check, and exposes only counts, never other customers' details.
 */
class AvailabilityController extends Controller
{
    public function check(Request $request, BookingAvailability $availability)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $max = (int) config('catering.max_events_per_day');
        $day = Carbon::createFromFormat('Y-m-d', $data['date']);

        $taken = Booking::whereDate('event_date', $data['date'])
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->count();

        // lock=false: this is only a preview, the real check re-runs inside the booking transaction.
        $problem = $availability->conflict($data['date'], $data['time'] ?? null, $data['location'] ?? null, null, false);

        // Fully booked dates in the same month, so the customer can pick another day.
        $fullDates = Booking::query()
            ->selectRaw('event_date, COUNT(*) as total')
            ->whereBetween('event_date', [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()])
            ->whereDate('event_date', '>=', today())
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->groupBy('event_date')
            ->havingRaw('COUNT(*) >= ?', [$max])
            ->get()
            ->map(fn ($row) => Carbon::parse($row->event_date)->format('M j'))
            ->values();

        return response()->json([
            'available' => $problem === null,
            'message' => $problem ?? 'Good news, this date is available.',
            'remaining' => max(0, $max - $taken),
            'full_dates' => $fullDates,
        ]);
    }
}
