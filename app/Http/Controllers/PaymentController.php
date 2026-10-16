<?php
// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends CrudController
{
    private const METHODS = ['Cash', 'GCash', 'Bank Transfer', 'Credit Card'];
    private const STATUSES = ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed'];

    protected function model(): string { return Payment::class; }
    protected function routeBase(): string { return 'admin.payments'; }
    protected function label(): string { return 'Payment'; }

    public function index(Request $request)
    {
        $payments = Payment::with(['user', 'booking'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Cards describe all payments, not only the visible page.
        $stats = [
            'total' => (float) Payment::sum('amount'),
            'paid' => Payment::where('status', 'paid')->count(),
            'pending' => Payment::where('status', 'pending')->count(),
        ];

        return view('administrator.payments', compact('payments', 'stats'));
    }

    // Wrapped in a transaction so the balance check and the insert can't interleave.
    public function store(Request $request)
    {
        return DB::transaction(fn () => parent::store($request));
    }

    public function update(Request $request, $id)
    {
        return DB::transaction(fn () => parent::update($request, $id));
    }

    protected function fields(Model $item): array
    {
        // Newest 300 live bookings (plus this payment's own) keeps the dropdown fast.
        $bookings = Booking::withPaid()
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhere('id', $item->booking_id))
            ->orderByDesc('id')->limit(300)->get()
            ->mapWithKeys(fn ($b) => [$b->id => "#{$b->id} - {$b->event_name} (total ₱" . number_format($b->total_amount, 2)
                . ', balance ₱' . number_format($b->balance, 2) . ')'])
            ->all();

        return [
            ['name' => 'booking_id', 'label' => 'Booking', 'type' => 'select', 'required' => true, 'options' => $bookings],
            ['name' => 'amount', 'label' => 'Amount (₱)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'payment_method', 'label' => 'Method', 'type' => 'select',
                'options' => array_combine(self::METHODS, self::METHODS)],
            ['name' => 'payment_date', 'label' => 'Payment date', 'type' => 'date'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => self::STATUSES],
            ['name' => 'reference_number', 'label' => 'Reference number', 'type' => 'text'],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_method' => ['nullable', 'in:' . implode(',', self::METHODS)],
            'payment_date' => ['nullable', 'date'],
            'status' => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item = null): array
    {
        $booking = Booking::lockForUpdate()->findOrFail($data['booking_id']);

        $this->guardBalance($booking, $data, $item);

        // A payment always belongs to the customer who owns the booking.
        $data['user_id'] = $booking->user_id;

        // A received payment with no date is dated today.
        if ($data['status'] === 'paid' && empty($data['payment_date'])) {
            $data['payment_date'] = now()->toDateString();
        }

        return $data;
    }

    /** Rejects payments on cancelled bookings and anything above the remaining balance. */
    private function guardBalance(Booking $booking, array $data, ?Model $item): void
    {
        if ($booking->status === 'cancelled' && ! $item) {
            throw ValidationException::withMessages(['booking_id' => 'This booking is cancelled; no new payments can be recorded.']);
        }

        if ($data['status'] !== 'paid') {
            return;
        }

        $alreadyPaid = (float) $booking->payments()
            ->where('status', 'paid')
            ->when($item, fn ($q) => $q->where('id', '!=', $item->id))
            ->sum('amount');

        $remaining = round((float) $booking->total_amount - $alreadyPaid, 2);

        if ((float) $data['amount'] > $remaining + 0.004) {
            throw ValidationException::withMessages([
                'amount' => 'Amount exceeds the remaining balance of ₱' . number_format(max($remaining, 0), 2) . '.',
            ]);
        }
    }
}
