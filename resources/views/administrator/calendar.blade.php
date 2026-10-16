{{-- resources/views/administrator/calendar.blade.php --}}
@extends('layouts.admin')

@section('title', 'Calendar')

@section('content')

<div class="page-header">
    <div>
        <h1>Booking Calendar</h1>
        <p>Every pending, confirmed and completed booking. Red days are at the daily limit ({{ $cap }} events).</p>
    </div>

    <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary">+ New Booking</a>
</div>

<div class="card">
    <div class="cal-toolbar">
        <a class="btn btn-small btn-info" href="{{ route('admin.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">‹ Prev</a>
        <span>{{ $month->format('F Y') }}</span>
        <a class="btn btn-small btn-info" href="{{ route('admin.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">Next ›</a>
        <a class="btn btn-small btn-success" href="{{ route('admin.calendar') }}">Today</a>
    </div>

    <div class="cal-wrap">
        <div class="acal">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                <div class="dow">{{ $dow }}</div>
            @endforeach

            @foreach (\Carbon\CarbonPeriod::create($start, $end) as $day)
                @php
                    $items = $byDate->get($day->format('Y-m-d'), collect());
                    $live = $items->whereIn('status', ['pending', 'confirmed'])->count();
                @endphp

                <div class="cell {{ $day->month === $month->month ? '' : 'out' }} {{ $day->isToday() ? 'today' : '' }} {{ $live >= $cap ? 'full' : '' }}">
                    <div class="num">{{ $day->day }} @if ($live >= $cap)<small>FULL</small>@endif</div>

                    @foreach ($items->take(4) as $b)
                        <a class="chip {{ $b->status }}" href="{{ route('admin.bookings.show', $b->id) }}"
                           title="{{ $b->event_name }} - {{ $b->user->name ?? '' }} ({{ $b->status }})">
                            {{ $b->event_time ? \Carbon\Carbon::parse($b->event_time)->format('g:ia') . ' ' : '' }}{{ $b->event_name }}
                        </a>
                    @endforeach

                    @if ($items->count() > 4)
                        <small style="color:var(--muted)">+{{ $items->count() - 4 }} more</small>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div style="display:flex;gap:16px;margin-top:14px;font-size:12px;color:var(--muted);flex-wrap:wrap">
        <span>🟨 Pending</span><span>🟩 Confirmed</span><span>⬜ Completed</span>
    </div>
</div>

@endsection
