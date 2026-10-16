<?php
// app/Http/Controllers/ReceiptController.php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

/** Printable receipt (use the browser's Print / Save as PDF). */
class ReceiptController extends Controller
{
    public function show(Request $request, $id)
    {
        $payment = Payment::with(['booking.package', 'booking.user'])->findOrFail($id);

        // Customers may only open receipts for their own payments.
        if ($request->user()->role === 'user') {
            abort_unless($payment->user_id === $request->user()->id, 404);
        }

        // Only money actually received gets a receipt.
        abort_unless($payment->status === 'paid', 404);

        $booking = $payment->booking;

        return view('receipts.show', [
            'payment' => $payment,
            'booking' => $booking,
            'paidToDate' => $booking->amount_paid,
            'balance' => $booking->balance,
        ]);
    }
}
