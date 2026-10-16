{{-- resources/views/administrator/event-sheet.blade.php --}}
@extends('layouts.admin')

@section('title', 'Day sheet')

@php
    $time = $event->event_time ?: $booking?->event_time;
    $guests = $event->guests ?: $booking?->guests;
@endphp

@section('content')

<div class="page-header">
    <div>
        <h1>Day sheet: {{ $event->event_name }}</h1>
        <p>Everything the team needs on the day. Print it and bring it along.</p>
    </div>

    <div class="button-group">
        <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
        <a class="btn btn-info" href="{{ route('admin.events.index') }}">← Events</a>
    </div>
</div>

<div class="sheet-page">

    <div class="sheet-block">
        <h3>Event</h3>
        <dl class="kv">
            <dt>Date & time</dt><dd>{{ $event->event_date->format('l, F j, Y') }}@if ($time) · {{ \Carbon\Carbon::parse($time)->format('g:i A') }}@endif</dd>
            <dt>Venue</dt><dd>{{ $event->location ?: ($booking->location ?? '—') }}</dd>
            <dt>Guests</dt><dd>{{ $guests ?: '—' }}</dd>
            <dt>Type / status</dt><dd>{{ $event->event_type ?: '—' }} · {{ ucfirst($event->status) }}</dd>
            @if ($event->notes)<dt>Event notes</dt><dd>{{ $event->notes }}</dd>@endif
        </dl>
    </div>

    @if ($booking)
        <div class="sheet-block">
            <h3>Client & payment</h3>
            <dl class="kv">
                <dt>Client</dt><dd>{{ $booking->user->name ?? '—' }}</dd>
                <dt>Phone</dt><dd>{{ $booking->user->phone ?? '—' }}</dd>
                <dt>E-mail</dt><dd>{{ $booking->user->email ?? '—' }}</dd>
                <dt>Booking</dt><dd>#{{ $booking->id }} · {{ ucfirst($booking->status) }}</dd>
                <dt>Total</dt><dd>₱{{ number_format((float) $booking->total_amount, 2) }}</dd>
                <dt>Balance to collect</dt><dd>₱{{ number_format((float) $booking->balance, 2) }} ({{ ucfirst($booking->payment_status) }})</dd>
                @if ($booking->notes)<dt>Client notes</dt><dd>{{ $booking->notes }}</dd>@endif
            </dl>
        </div>

        <div class="sheet-block">
            <h3>Menu — {{ $booking->package->name ?? 'No package' }}</h3>
            @forelse ($booking->package?->menuItems ?? [] as $menu)
                <div style="padding:5px 0;border-bottom:1px solid #f1f1f1">
                    <strong>{{ $menu->name }}</strong>
                    @if ($menu->category)<span style="color:var(--muted)"> · {{ $menu->category }}</span>@endif
                </div>
            @empty
                <p style="color:var(--muted)">No menu items are attached to this package.</p>
            @endforelse
        </div>
    @endif

    <div class="sheet-block">
        <h3>Team assigned</h3>
        @forelse ($event->staff as $member)
            <div style="padding:5px 0;border-bottom:1px solid #f1f1f1"><strong>{{ $member->name }}</strong>
                <span style="color:var(--muted)"> · {{ $member->position ?? 'Staff' }}@if (! empty($member->phone)) · {{ $member->phone }}@endif</span></div>
        @empty
            <p style="color:var(--muted)">Nobody assigned yet.</p>
        @endforelse
    </div>

    <div class="sheet-block">
        <h3>Supplies reserved</h3>
        @forelse ($event->inventoryItems as $item)
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f1f1">
                <span>☐ {{ $item->name }}</span>
                <strong>{{ rtrim(rtrim(number_format((float) $item->pivot->quantity, 2, '.', ''), '0'), '.') }} {{ $item->unit }}</strong>
            </div>
        @empty
            <p style="color:var(--muted)">No supplies reserved. Use "Supplies" on the events list.</p>
        @endforelse
    </div>

</div>

@endsection
