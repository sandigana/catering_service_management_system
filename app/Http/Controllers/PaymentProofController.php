<?php
// app/Http/Controllers/PaymentProofController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentProofSubmitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Customers upload a GCash / bank slip. It becomes a PENDING payment (it does not
 * reduce the balance) until the back office verifies it. The file is stored on the
 * private disk and can only be opened through proofs.show, which checks who is asking.
 */
class PaymentProofController extends Controller
{
    private const MAX_OPEN_SUBMISSIONS = 3;

    public function store(Request $request, $bookingId)
    {
        $booking = Booking::withPaid()->where('user_id', $request->user()->id)->findOrFail($bookingId);

        if (! in_array($booking->status, Booking::ACTIVE_STATUSES, true) || (float) $booking->balance <= 0) {
            return back()->withErrors(['proof' => 'This booking has nothing left to pay online.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'payment_method' => ['required', 'in:GCash,Bank Transfer'],
            'reference_number' => ['required', 'string', 'max:100'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:' . config('catering.proof_max_kb')],
        ]);

        if ((float) $data['amount'] > (float) $booking->balance + 0.004) {
            throw ValidationException::withMessages(['amount' => 'The amount is more than your balance of ₱' . number_format((float) $booking->balance, 2) . '.']);
        }

        $payment = DB::transaction(function () use ($request, $booking, $data) {
            Booking::lockForUpdate()->findOrFail($booking->id);

            $open = $booking->payments()->where('status', 'pending')->where('submitted_by_customer', true)->count();
            if ($open >= self::MAX_OPEN_SUBMISSIONS) {
                throw ValidationException::withMessages(['proof' => 'You already have ' . self::MAX_OPEN_SUBMISSIONS . ' payments waiting to be verified. Please wait for us to confirm them.']);
            }

            if ($booking->payments()->where('reference_number', $data['reference_number'])->exists()) {
                throw ValidationException::withMessages(['reference_number' => 'That reference number was already submitted for this booking.']);
            }

            // Random file name on the PRIVATE disk; the customer's file name is never used.
            $path = $request->file('proof')->store("payment-proofs/{$booking->id}", 'local');

            return Payment::create([
                'user_id' => $booking->user_id,
                'booking_id' => $booking->id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'payment_date' => now()->toDateString(),
                'status' => 'pending',
                'reference_number' => $data['reference_number'],
                'proof_path' => $path,
                'submitted_by_customer' => true,
            ]);
        });

        try {
            $payment->load('booking.user');
            User::where('is_active', true)->whereIn('role', ['admin', 'staff'])->get()
                ->each(fn (User $user) => $user->notify(new PaymentProofSubmitted($payment)));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('customer.bookings.show', $booking->id)
            ->with('success', 'Thank you! We received your payment proof and will verify it soon.');
    }

    /** Streams the slip to its owner or to the back office. Nobody else can see it. */
    public function show(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($request->user()->role === 'user') {
            abort_unless($payment->user_id === $request->user()->id, 404);
        }

        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
