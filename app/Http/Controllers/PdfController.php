<?php
// app/Http/Controllers/PdfController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\DocumentPdf;
use Illuminate\Http\Request;

/** PDF downloads: official receipt (paid payments only) and booking invoice. */
class PdfController extends Controller
{
    public function __construct(private DocumentPdf $documents)
    {
    }

    public function receipt(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($request->user()->role === 'user') {
            abort_unless($payment->user_id === $request->user()->id, 404);
        }

        abort_unless($payment->status === 'paid', 404);

        return $this->pdf($this->documents->receipt($payment), $payment->receipt_number . '.pdf');
    }

    public function invoice(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        if ($request->user()->role === 'user') {
            abort_unless($booking->user_id === $request->user()->id, 404);
        }

        return $this->pdf($this->documents->invoice($booking), 'invoice-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) . '.pdf');
    }

    private function pdf(string $content, string $filename)
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
