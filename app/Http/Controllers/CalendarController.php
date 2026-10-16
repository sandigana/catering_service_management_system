<?php
// app/Http/Controllers/CalendarController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Month view of every booking for admin and staff. */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        // ?month=YYYY-MM ; anything invalid falls back to the current month.
        try {
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
                ? Carbon::createFromFormat('Y-m-d', $request->query('month') . '-01')->startOfDay()
                : now()->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $byDate = Booking::with('user')
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('event_time')
            ->get()
            ->groupBy(fn ($b) => $b->event_date->format('Y-m-d'));

        return view('administrator.calendar', [
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'byDate' => $byDate,
            'cap' => (int) config('catering.max_events_per_day'),
        ]);
    }
}
