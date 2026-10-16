@extends('layouts.customer')

@section('title', 'My Bookings')

@section('content')

<div class="c-head">
    <div>
        <h1>My Bookings</h1>
        <p>Your catering requests and their status</p>
    </div>
    <a class="c-btn" href="{{ route('customer.bookings.create') }}">+ New Booking</a>
</div>

<div class="c-card">
    <table class="c-table">
        <thead>
            <tr><th>#</th><th>Event</th><th>Package</th><th>Date</th><th>Guests</th><th>Total</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($bookings as $booking)
            <tr>
                <td>{{ $booking->id }}</td>
                <td>{{ $booking->event_name }}</td>
                <td>{{ $booking->package->name ?? '—' }}</td>
                <td>{{ $booking->event_date->format('M d, Y') }}</td>
                <td>{{ $booking->guests }}</td>
                <td>₱{{ number_format($booking->total_amount, 2) }}</td>
                <td><span class="c-badge {{ strtolower($booking->status) }}">{{ ucfirst($booking->status) }}</span></td>
                <td><a href="{{ route('customer.bookings.show', $booking->id) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="8">You have no bookings yet.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $bookings->links() }}</div>
</div>

@endsection
