{{-- resources/views/receipts/show.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $payment->receipt_number }}</title>
    @include('partials.favicon')
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f6f7f3; color: #202b28; margin: 0; padding: 24px; }
        .sheet { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e8e9e3; border-radius: 14px; padding: 32px; }
        h1 { margin: 0; font-size: 22px; }
        h1 span { color: #ff8a00; }
        .muted { color: #748079; font-size: 13px; }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #ff8a00; padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { text-align: left; padding: 10px 6px; border-bottom: 1px solid #eee; font-size: 14px; }
        th { width: 40%; color: #68756d; font-weight: 600; }
        .total td, .total th { font-size: 16px; font-weight: 700; border-bottom: 0; }
        .actions { max-width: 640px; margin: 0 auto 14px; display: flex; gap: 10px; }
        .btn { padding: 10px 16px; border: 0; border-radius: 9px; background: #ff8a00; color: #fff; font: inherit; cursor: pointer; text-decoration: none; }
        .btn.alt { background: #e5e7eb; color: #111; }
        .stamp { display: inline-block; padding: 4px 12px; border: 2px solid #166534; color: #166534; border-radius: 6px; font-weight: 700; letter-spacing: .08em; transform: rotate(-4deg); }
        @media print { body { background: #fff; padding: 0; } .sheet { border: 0; } .actions { display: none; } }
    </style>
</head>
<body>

<div class="actions">
    <a class="btn" href="{{ route('receipts.pdf', $payment->id) }}">Download PDF</a>
    <button class="btn alt" onclick="window.print()">Print</button>
    <a class="btn alt" href="javascript:window.close()">Close</a>
</div>

<div class="sheet">
    <div class="head">
        <div>
            <h1>Catering <span>System</span></h1>
            <div class="muted">Official receipt</div>
        </div>
        <div style="text-align:right">
            <strong>{{ $payment->receipt_number }}</strong><br>
            <span class="muted">{{ ($payment->payment_date ?? $payment->created_at)->format('F d, Y') }}</span><br>
            <span class="stamp">PAID</span>
        </div>
    </div>

    <table>
        <tr><th>Received from</th><td>{{ $booking->user->name ?? '—' }}</td></tr>
        <tr><th>Booking</th><td>#{{ $booking->id }} — {{ $booking->event_name }}</td></tr>
        <tr><th>Package</th><td>{{ $booking->package->name ?? '—' }}</td></tr>
        <tr><th>Event date</th><td>{{ $booking->event_date->format('F d, Y') }}</td></tr>
        <tr><th>Guests</th><td>{{ $booking->guests }}</td></tr>
        <tr><th>Payment method</th><td>{{ $payment->payment_method ?? '—' }}</td></tr>
        <tr><th>Reference no.</th><td>{{ $payment->reference_number ?: '—' }}</td></tr>
    </table>

    <table>
        <tr><th>Booking total</th><td>₱{{ number_format((float) $booking->total_amount, 2) }}</td></tr>
        <tr><th>Paid to date</th><td>₱{{ number_format((float) $paidToDate, 2) }}</td></tr>
        <tr><th>Remaining balance</th><td>₱{{ number_format((float) $balance, 2) }}</td></tr>
        <tr class="total"><th>Amount of this receipt</th><td>₱{{ number_format((float) $payment->amount, 2) }}</td></tr>
    </table>

    <p class="muted">Thank you for choosing us. This receipt is system-generated.</p>
</div>

</body>
</html>
