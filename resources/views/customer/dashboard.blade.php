{{-- resources/views/customer/dashboard.blade.php --}}
@extends('layouts.customer')

@section('title', 'My Bookings')

@php
    $fmtTime = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('g:i A') : null;

    // Calendar grid: Monday-first weeks covering the selected month.
    $gridStart = $month->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
    $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $days = \Carbon\CarbonPeriod::create($gridStart, $gridEnd);
@endphp

@section('content')

<div class="cd-head">
    <h1>My Bookings</h1>

    <div class="cd-actions">
        <a href="{{ route('customer.bookings.create') }}" class="c-btn">＋ New Booking</a>
        <a href="{{ route('customer.packages') }}" class="icon-btn" title="Browse packages">📦</a>
    </div>
</div>

{{-- Featured booking --}}
@if ($nextBooking)
    <div class="order-card">
        <div class="order-top">
            <div class="order-id">🏷️ <b>Booking ID:</b> #{{ $nextBooking->id }}
                <span style="color:var(--muted);font-size:14px">· {{ $nextBooking->event_name }}</span>
            </div>

            <div class="order-meta">
                📅 <span>Event:</span>
                <strong>{{ $nextBooking->event_date->format('d.m.Y') }}@if ($fmtTime($nextBooking->event_time)) · {{ $fmtTime($nextBooking->event_time) }}@endif</strong>
                <span class="c-badge {{ strtolower($nextBooking->status) }}">{{ $nextBooking->status }}</span>
                <a class="c-btn alt" href="{{ route('customer.bookings.show', $nextBooking->id) }}">Open</a>
            </div>
        </div>

        <div class="chip-row">
            <div class="chip">💳 <div><b>Payment:</b>
                <span class="c-badge {{ $nextBooking->payment_status === 'paid' ? 'paid' : ($nextBooking->payment_status === 'partial' ? 'partial' : 'unpaid') }}">{{ $nextBooking->payment_status }}</span>
                ₱{{ number_format((float) $nextBooking->amount_paid, 2) }} / ₱{{ number_format((float) $nextBooking->total_amount, 2) }}</div>
            </div>
            <div class="chip">🍽️ <div><b>Package:</b> {{ $nextBooking->package->name ?? '—' }}, {{ $nextBooking->guests }} guests</div></div>
            <div class="chip">📍 <div><b>Venue:</b> {{ $nextBooking->location ?: '—' }}</div></div>
        </div>
    </div>
@else
    <div class="order-card empty-order">
        <p style="font-size:16px;margin-bottom:12px">You have no bookings yet.</p>
        <a class="c-btn" href="{{ route('customer.bookings.create') }}">Book your first event</a>
    </div>
@endif

<div class="cd-grid">

    {{-- Calendar --}}
    <div>
        <h2>Event Calendar</h2>

        <div class="cal">
            <div class="cal-nav">
                <a href="{{ route('customer.dashboard', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" aria-label="Previous month">‹</a>
                <span>{{ $month->format('F Y') }}</span>
                <a href="{{ route('customer.dashboard', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" aria-label="Next month">›</a>
            </div>

            <div class="cal-grid">
                @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $dow)
                    <div class="cal-dow">{{ $dow }}</div>
                @endforeach

                @foreach ($days as $day)
                    @php
                        $key = $day->format('Y-m-d');
                        $booking = $monthBookings->get($key)?->first();
                        $inMonth = $day->month === $month->month;

                        $state = '';
                        if ($booking) {
                            $state = $booking->status === 'cancelled' ? 'cancelled'
                                : ($booking->status === 'pending' ? 'pending'
                                : ($day->isFuture() || $day->isToday() ? 'up' : 'past'));
                        }
                    @endphp

                    @if ($booking)
                        <a class="day {{ $state }} {{ $inMonth ? '' : 'out' }} {{ $day->isToday() ? 'today' : '' }}"
                           href="{{ route('customer.bookings.show', $booking->id) }}"
                           title="{{ $booking->event_name }} ({{ $booking->status }})">
                            {{ $day->day }}
                            <small>{{ $state === 'past' ? '✓ Done' : ucfirst($booking->status) }}</small>
                        </a>
                    @else
                        <div class="day {{ $inMonth ? '' : 'out' }} {{ $day->isToday() ? 'today' : '' }}">{{ $day->day }}</div>
                    @endif
                @endforeach
            </div>

            <div class="cal-legend">
                <span><i style="background:var(--brand)"></i>Confirmed</span>
                <span><i style="background:var(--tint);border:1px dashed var(--brand)"></i>Pending</span>
                <span><i style="background:#fff;border:1px solid var(--line)"></i>Done</span>
                <span><i style="background:#f1f3f2"></i>Cancelled / free</span>
            </div>
        </div>
    </div>

    {{-- Side cards --}}
    <div>
        <div class="side-card one">
            <span class="ico">⭐</span>
            <h3>Planning another event?</h3>
            <p>Browse our packages and pick the menu that fits your guests.</p>
            <a class="pill" href="{{ route('customer.packages') }}">View packages</a>
        </div>

        <div class="side-card two">
            <span class="ico">💰</span>
            <h3>Balance due</h3>
            <p style="font-size:22px;font-weight:700;color:var(--ink);margin-bottom:4px">₱{{ number_format($balanceDue, 2) }}</p>
            <p>Across your {{ $myBookings }} booking{{ $myBookings === 1 ? '' : 's' }}.</p>
            <a class="pill light" href="{{ route('customer.bookings.index') }}">See my bookings</a>
        </div>
    </div>
</div>

@endsection
