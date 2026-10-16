<?php
// app/Services/DocumentPdf.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Support\SimplePdf;

/** Builds the official receipt and the booking invoice as real PDF files. */
class DocumentPdf
{
    private const BRAND = [1.0, 0.54, 0.0];   // orange
    private const MUTED = [0.42, 0.45, 0.5];
    private const INK = [0.12, 0.16, 0.22];

    private const LEFT = 48;
    private const RIGHT = 547;

    public function receipt(Payment $payment): string
    {
        $payment->loadMissing('booking.package', 'booking.user');
        $booking = $payment->booking;
        $pdf = new SimplePdf;

        $this->header($pdf, 'OFFICIAL RECEIPT', $payment->receipt_number, ($payment->payment_date ?? $payment->created_at)->format('F j, Y'));

        // PAID stamp
        $pdf->rect(430, 118, 117, 34, null, [0.09, 0.4, 0.2], 1.6);
        $pdf->text(488, 141, 'PAID', 20, true, [0.09, 0.4, 0.2], 'center');

        $y = 130;
        $y = $this->pairs($pdf, $y, [
            'Received from' => $booking->user->name ?? '-',
            'Booking' => "#{$booking->id} - {$booking->event_name}",
            'Package' => $booking->package->name ?? '-',
            'Event date' => $booking->event_date->format('F j, Y'),
            'Guests' => (string) $booking->guests,
            'Payment method' => $payment->payment_method ?: '-',
            'Reference no.' => $payment->reference_number ?: '-',
        ], 300);

        $y += 14;
        $pdf->line(self::LEFT, $y, self::RIGHT, $y);
        $y += 22;
        $y = $this->money($pdf, $y, 'Booking total', $booking->total_amount);
        $y = $this->money($pdf, $y, 'Paid to date', $booking->amount_paid);
        $y = $this->money($pdf, $y, 'Remaining balance', $booking->balance);

        $y += 10;
        $pdf->rect(self::LEFT, $y, self::RIGHT - self::LEFT, 40, [1, 0.95, 0.88]);
        $pdf->text(self::LEFT + 14, $y + 25, 'Amount of this receipt', 12, true);
        $pdf->text(self::RIGHT - 14, $y + 26, $this->peso($payment->amount), 16, true, self::BRAND, 'right');

        $this->footer($pdf, 'Thank you for choosing us. This receipt is system-generated and valid without a signature.');

        return $pdf->output();
    }

    public function invoice(Booking $booking): string
    {
        $booking->loadMissing('package', 'user', 'payments');
        $pdf = new SimplePdf;

        $this->header($pdf, 'INVOICE', 'INV-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT), now()->format('F j, Y'));

        // Bill to / event
        $pdf->text(self::LEFT, 134, 'BILL TO', 8, true, self::MUTED);
        $pdf->text(self::LEFT, 150, $booking->user->name ?? '-', 12, true);
        $pdf->text(self::LEFT, 165, $booking->user->email ?? '', 9, false, self::MUTED);
        if ($booking->user?->phone) {
            $pdf->text(self::LEFT, 178, $booking->user->phone, 9, false, self::MUTED);
        }

        $when = $booking->event_date->format('F j, Y') . ($booking->event_time ? ' at ' . \Carbon\Carbon::parse($booking->event_time)->format('g:i A') : '');
        $pdf->text(self::RIGHT, 134, 'EVENT', 8, true, self::MUTED, 'right');
        $pdf->text(self::RIGHT, 150, $booking->event_name, 12, true, self::INK, 'right');
        $pdf->text(self::RIGHT, 165, $when, 9, false, self::MUTED, 'right');
        if ($booking->location) {
            $pdf->text(self::RIGHT, 178, $booking->location, 9, false, self::MUTED, 'right');
        }

        // Charges
        $y = 212;
        $pdf->rect(self::LEFT, $y, self::RIGHT - self::LEFT, 24, [0.18, 0.18, 0.18]);
        $pdf->text(self::LEFT + 10, $y + 16, 'Description', 9, true, [1, 1, 1]);
        $pdf->text(self::RIGHT - 10, $y + 16, 'Amount', 9, true, [1, 1, 1], 'right');
        $y += 44;

        $unit = $booking->package ? $this->peso($booking->package->price) . ' x ' . $booking->guests . ' guests' : $booking->guests . ' guests';
        $pdf->text(self::LEFT + 10, $y, ($booking->package->name ?? 'Catering service'), 10, true);
        $pdf->text(self::LEFT + 10, $y + 13, $unit, 9, false, self::MUTED);
        $pdf->text(self::RIGHT - 10, $y, $this->peso($booking->total_amount), 10, true, self::INK, 'right');
        $y += 34;
        $pdf->line(self::LEFT, $y, self::RIGHT, $y);

        // Payments
        $y += 28;
        $pdf->text(self::LEFT, $y, 'PAYMENTS', 8, true, self::MUTED);
        $y += 16;

        $payments = $booking->payments->sortBy('id');
        if ($payments->isEmpty()) {
            $pdf->text(self::LEFT, $y, 'No payments recorded yet.', 9, false, self::MUTED);
            $y += 16;
        }

        foreach ($payments as $payment) {
            if ($y > 740) {                      // continue on a new page for long histories
                $pdf->addPage();
                $y = 60;
            }

            $date = ($payment->payment_date ?? $payment->created_at)->format('M j, Y');
            $pdf->text(self::LEFT, $y, $date, 9);
            $pdf->text(self::LEFT + 90, $y, $payment->payment_method ?: '-', 9);
            $pdf->text(self::LEFT + 200, $y, ucfirst($payment->status) . ($payment->reference_number ? '  #' . $payment->reference_number : ''), 9, false, self::MUTED);
            $pdf->text(self::RIGHT - 10, $y, $this->peso($payment->amount), 9, false, self::INK, 'right');
            $y += 16;
        }

        $y += 8;
        $pdf->line(self::LEFT, $y, self::RIGHT, $y);
        $y += 24;
        $y = $this->money($pdf, $y, 'Total', $booking->total_amount);
        $y = $this->money($pdf, $y, 'Paid to date', $booking->amount_paid);

        $pdf->rect(self::LEFT + 250, $y - 8, self::RIGHT - self::LEFT - 250, 34, [1, 0.95, 0.88]);
        $pdf->text(self::LEFT + 264, $y + 14, 'Balance due', 12, true);
        $pdf->text(self::RIGHT - 12, $y + 14, $this->peso($booking->balance), 14, true, self::BRAND, 'right');

        $this->footer($pdf, 'Please keep this invoice for your records. Payment status: ' . strtoupper($booking->payment_status) . '.');

        return $pdf->output();
    }

    /* ---------- layout helpers ---------- */

    private function header(SimplePdf $pdf, string $title, string $number, string $date): void
    {
        $pdf->rect(0, 0, SimplePdf::WIDTH, 96, [0.18, 0.18, 0.18]);
        $pdf->rect(0, 90, SimplePdf::WIDTH, 6, self::BRAND);
        $pdf->text(self::LEFT, 46, config('app.name', 'Catering System'), 20, true, [1, 1, 1]);
        $pdf->text(self::LEFT, 64, $title, 10, true, self::BRAND);
        $pdf->text(self::RIGHT, 46, $number, 13, true, [1, 1, 1], 'right');
        $pdf->text(self::RIGHT, 64, $date, 10, false, [0.8, 0.8, 0.8], 'right');
    }

    private function footer(SimplePdf $pdf, string $note): void
    {
        $pdf->line(self::LEFT, 790, self::RIGHT, 790);
        $pdf->text(SimplePdf::WIDTH / 2, 808, $note, 8, false, self::MUTED, 'center');
    }

    /** Label/value rows; returns the next free y. */
    private function pairs(SimplePdf $pdf, float $y, array $rows, float $maxValueWidth): float
    {
        foreach ($rows as $label => $value) {
            $y += 22;
            $pdf->text(self::LEFT, $y, $label, 10, true, self::MUTED);
            $pdf->text(self::LEFT + 130, $y, $this->fit($pdf, (string) $value, 10, $maxValueWidth), 10);
        }

        return $y;
    }

    private function money(SimplePdf $pdf, float $y, string $label, float|string $amount): float
    {
        $pdf->text(self::LEFT + 264, $y, $label, 10, false, self::MUTED);
        $pdf->text(self::RIGHT - 12, $y, $this->peso($amount), 10, true, self::INK, 'right');

        return $y + 20;
    }

    /** Shortens text with "..." so it can never run off the page. */
    private function fit(SimplePdf $pdf, string $text, float $size, float $maxWidth): string
    {
        while ($text !== '' && $pdf->textWidth($text, $size) > $maxWidth) {
            $text = mb_substr($text, 0, -2) . '…';
            if (mb_strlen($text) < 4) {
                break;
            }
        }

        return $text;
    }

    private function peso(float|string $amount): string
    {
        return 'PHP ' . number_format((float) $amount, 2);
    }
}
