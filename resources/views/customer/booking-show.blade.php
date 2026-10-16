@extends('layouts.customer')

@section('title', 'Booking #' . $booking->id)

@section('content')

<div class="c-head">
    <div>
        <h1>{{ $booking->event_name }}</h1>
        <p>Booking #{{ $booking->id }} · <span class="c-badge {{ strtolower($booking->status) }}">{{ ucfirst($booking->status) }}</span></p>
    </div>
    <div class="cd-actions">
        <a class="c-btn alt" href="{{ route('invoices.pdf', $booking->id) }}" target="_blank">📄 Invoice (PDF)</a>
        <a class="c-btn alt" href="{{ route('customer.bookings.index') }}">← My Bookings</a>
    </div>
</div>

<div class="c-card">
    <table class="c-table">
        <tr><th>Package</th><td>{{ $booking->package->name ?? '—' }}</td></tr>
        <tr><th>Date</th><td>{{ $booking->event_date->format('F d, Y') }}@if ($booking->event_time) at {{ \Carbon\Carbon::createFromFormat('H:i:s', strlen($booking->event_time) === 5 ? $booking->event_time . ':00' : $booking->event_time)->format('g:i A') }}@endif</td></tr>
        <tr><th>Guests</th><td>{{ $booking->guests }}</td></tr>
        <tr><th>Location</th><td>{{ $booking->location ?? '—' }}</td></tr>
        <tr><th>Total</th><td>₱{{ number_format($booking->total_amount, 2) }}</td></tr>
        <tr><th>Paid</th><td>₱{{ number_format((float) $booking->amount_paid, 2) }}</td></tr>
        <tr><th>Balance</th><td>₱{{ number_format((float) $booking->balance, 2) }} <span class="c-badge {{ $booking->payment_status === 'paid' ? 'paid' : ($booking->payment_status === 'partial' ? 'pending' : 'cancelled') }}">{{ ucfirst($booking->payment_status) }}</span></td></tr>
        <tr><th>Notes</th><td>{{ $booking->notes ?: '—' }}</td></tr>
    </table>
</div>

<div class="c-card">
    <h3>Payments</h3>
    <table class="c-table">
        <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
        <tbody>
        @forelse ($booking->payments as $payment)
            <tr>
                <td>{{ $payment->payment_date?->format('M d, Y') ?? '—' }}</td>
                <td>{{ $payment->payment_method ?? '—' }}</td>
                <td>₱{{ number_format($payment->amount, 2) }}</td>
                <td><span class="c-badge {{ strtolower($payment->status) }}">{{ ucfirst($payment->status) }}</span></td>
                <td>
                    @if ($payment->status === 'paid')
                        <a href="{{ route('receipts.show', $payment->id) }}" target="_blank">{{ $payment->receipt_number }}</a>
                        · <a href="{{ route('receipts.pdf', $payment->id) }}" target="_blank">PDF</a>
                    @elseif ($payment->status === 'pending' && $payment->submitted_by_customer)
                        <span style="color:var(--muted)">Waiting for verification</span>
                        @if ($payment->proof_path)· <a href="{{ route('proofs.show', $payment->id) }}" target="_blank">your slip</a>@endif
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No payments recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@php
    $canPay = in_array($booking->status, ['pending', 'confirmed'], true) && (float) $booking->balance > 0;
    $latestRequest = $booking->rescheduleRequests()->latest('id')->first();
@endphp

@if ($canPay)
<div class="c-card">
    <h3>Pay by GCash or bank transfer</h3>
    <p style="margin:8px 0 14px;color:#6b7280;">Send your payment, then upload a screenshot or photo of the receipt. We will verify it and e-mail your official receipt. Balance now: <strong>₱{{ number_format((float) $booking->balance, 2) }}</strong></p>

    <form method="POST" action="{{ route('customer.bookings.payments.store', $booking->id) }}" enctype="multipart/form-data" class="c-form">
        @csrf

        <div>
            <label for="amount">Amount paid (₱)</label>
            <input id="amount" type="number" name="amount" step="0.01" min="1" max="{{ $booking->balance }}" value="{{ old('amount') }}" required>
        </div>

        <div>
            <label for="payment_method">Paid through</label>
            <select id="payment_method" name="payment_method" required>
                @foreach (['GCash', 'Bank Transfer'] as $method)
                    <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="reference_number">Reference number</label>
            <input id="reference_number" type="text" name="reference_number" maxlength="100" value="{{ old('reference_number') }}" required>
        </div>

        <div>
            <label for="proof">Receipt screenshot / photo (JPG, PNG, WebP or PDF, up to {{ round(config('catering.proof_max_kb') / 1024) }} MB)</label>
            <input id="proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" required>
        </div>

        <div class="full"><button type="submit" class="c-btn">Send proof of payment</button></div>
    </form>
</div>
@endif

@if ($latestRequest && $latestRequest->status === 'pending')
<div class="c-card">
    <h3>Date change requested</h3>
    <p style="margin:8px 0 14px;color:#6b7280;">
        You asked to move this event to <strong>{{ $latestRequest->requested_date->format('F d, Y') }}</strong>
        @if ($latestRequest->requested_time) at <strong>{{ \Carbon\Carbon::parse($latestRequest->requested_time)->format('g:i A') }}</strong>@endif.
        We will e-mail you our decision. Your original date stays until then.
    </p>
    <form method="POST" action="{{ route('customer.bookings.reschedule.withdraw', $booking->id) }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="c-btn alt">Withdraw request</button>
    </form>
</div>
@elseif ($booking->isReschedulableByCustomer())
<div class="c-card">
    <h3>Need a different date?</h3>
    @if ($latestRequest && $latestRequest->status === 'rejected')
        <p style="margin:8px 0;color:#b91c1c;">Your last request was declined.@if ($latestRequest->admin_note) Note: {{ $latestRequest->admin_note }}@endif</p>
    @endif
    <p style="margin:8px 0 14px;color:#6b7280;">Ask for a new date and time. It must be at least {{ config('catering.reschedule_min_days') }} days from today and still available.</p>

    <form method="POST" action="{{ route('customer.bookings.reschedule', $booking->id) }}" class="c-form">
        @csrf

        <div>
            <label for="requested_date">New date</label>
            <input id="requested_date" type="date" name="requested_date" min="{{ now()->addDays(config('catering.reschedule_min_days'))->toDateString() }}" value="{{ old('requested_date') }}" required>
        </div>

        <div>
            <label for="requested_time">New start time</label>
            <input id="requested_time" type="time" name="requested_time" value="{{ old('requested_time') }}" required>
        </div>

        <div class="full">
            <label for="reason">Reason (optional)</label>
            <input id="reason" type="text" name="reason" maxlength="500" value="{{ old('reason') }}">
        </div>

        <div class="full"><button type="submit" class="c-btn">Request date change</button></div>
    </form>
</div>
@endif

@if ($booking->isCancellableByCustomer())
<div class="c-card">
    <h3>Need to cancel?</h3>
    <p style="margin:8px 0 14px;color:#6b7280;">You can cancel online until the day before your event.</p>
    <form method="POST" action="{{ route('customer.bookings.cancel', $booking->id) }}"
          onsubmit="return confirm('Cancel this booking? This cannot be undone.')">
        @csrf
        @method('PATCH')
        <button type="submit" class="c-btn" style="background:#b91c1c;">Cancel booking</button>
    </form>
</div>
@endif

@endsection
