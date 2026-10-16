<?php
// app/Http/Controllers/RescheduleController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\RescheduleRequest;
use App\Models\User;
use App\Notifications\RescheduleDecided;
use App\Notifications\RescheduleRequested;
use App\Services\BookingAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customers ask for a new date/time; admin and staff approve or reject.
 * Approving re-runs the real availability check, so a date can never be double-booked.
 */
class RescheduleController extends Controller
{
    public function __construct(private BookingAvailability $availability)
    {
    }

    /* ---------------- customer ---------------- */

    public function store(Request $request, $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($bookingId);

        if (! $booking->isReschedulableByCustomer()) {
            return back()->withErrors(['reschedule' => 'This booking cannot be rescheduled online right now. It may already have a request waiting, or the event is too close. Please contact us.']);
        }

        $earliest = today()->addDays((int) config('catering.reschedule_min_days'))->toDateString();

        $data = $request->validate([
            'requested_date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$earliest}"],
            'requested_time' => ['required', 'date_format:H:i,H:i:s'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $data['requested_time'] = substr($data['requested_time'], 0, 5);

        $sameSlot = $booking->event_date->toDateString() === $data['requested_date']
            && substr((string) $booking->event_time, 0, 5) === $data['requested_time'];

        if ($sameSlot) {
            throw ValidationException::withMessages(['requested_date' => 'That is already the date and time of your booking.']);
        }

        // Refuse impossible requests up front so staff never have to.
        if ($problem = $this->availability->conflict($data['requested_date'], $data['requested_time'], $booking->location, $booking->id, false)) {
            throw ValidationException::withMessages(['requested_date' => $problem]);
        }

        $rescheduleRequest = DB::transaction(function () use ($booking, $data) {
            // Only one open request per booking, even if the button is double-clicked.
            Booking::lockForUpdate()->findOrFail($booking->id);

            if ($booking->rescheduleRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reschedule' => 'You already have a request waiting for a decision.']);
            }

            return $booking->rescheduleRequests()->create($data + ['status' => 'pending']);
        });

        $this->safely(fn () => User::where('is_active', true)->whereIn('role', ['admin', 'staff'])->get()
            ->each(fn (User $user) => $user->notify(new RescheduleRequested($rescheduleRequest->load('booking.user')))));

        return redirect()->route('customer.bookings.show', $booking->id)
            ->with('success', 'Your request was sent. We will e-mail you our decision.');
    }

    public function withdraw(Request $request, $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($bookingId);

        $booking->rescheduleRequests()->where('status', 'pending')->get()
            ->each(fn (RescheduleRequest $r) => $r->update(['status' => 'withdrawn', 'decided_at' => now()]));

        return back()->with('success', 'Your reschedule request was withdrawn.');
    }

    /* ---------------- back office ---------------- */

    public function index()
    {
        return view('administrator.reschedules', [
            'pending' => RescheduleRequest::pending()->with(['booking.user', 'booking.package'])->oldest()->get(),
            'recent' => RescheduleRequest::whereIn('status', ['approved', 'rejected'])
                ->with(['booking.user', 'decider'])->latest('decided_at')->take(15)->get(),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $notify = DB::transaction(function () use ($request, $id) {
            $rescheduleRequest = RescheduleRequest::lockForUpdate()->findOrFail($id);
            abort_unless($rescheduleRequest->status === 'pending', 409);

            $booking = Booking::lockForUpdate()->findOrFail($rescheduleRequest->booking_id);

            if (! in_array($booking->status, Booking::ACTIVE_STATUSES, true)) {
                throw ValidationException::withMessages(['reschedule' => 'This booking is no longer active, so it cannot be moved.']);
            }

            $date = $rescheduleRequest->requested_date->toDateString();
            $time = $rescheduleRequest->requested_time ? substr($rescheduleRequest->requested_time, 0, 5) : null;

            // Same rules as a brand-new booking, ignoring the booking being moved.
            $this->availability->assertAvailable($date, $time, $booking->location, $booking->id);

            $booking->update(['event_date' => $date, 'event_time' => $time, 'reminder_sent_at' => null]);

            // Keep the linked event record in step with the booking.
            $booking->event?->update(['event_date' => $date, 'event_time' => $time]);

            $rescheduleRequest->update([
                'status' => 'approved',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            return $rescheduleRequest;
        });

        $this->safely(fn () => $notify->booking->user?->notify(new RescheduleDecided($notify->fresh('booking.user'))));

        return back()->with('success', 'Approved. The booking now has the new date.');
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $rescheduleRequest = DB::transaction(function () use ($request, $id, $data) {
            $row = RescheduleRequest::lockForUpdate()->findOrFail($id);
            abort_unless($row->status === 'pending', 409);

            $row->update([
                'status' => 'rejected',
                'admin_note' => $data['admin_note'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            return $row;
        });

        $this->safely(fn () => $rescheduleRequest->booking->user?->notify(new RescheduleDecided($rescheduleRequest->load('booking.user'))));

        return back()->with('success', 'Request rejected. The customer was notified.');
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
