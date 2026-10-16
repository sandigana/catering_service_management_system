{{-- resources/views/administrator/reschedules.blade.php --}}
@extends('layouts.admin')

@section('title', 'Reschedule Requests')

@php
    $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('g:i A') : '—';
@endphp

@section('content')

<div class="page-header">
    <div>
        <h1>Reschedule Requests</h1>
        <p>Customers asking to move their event. Approving re-checks availability, so a date can never be double-booked.</p>
    </div>
</div>

<div class="card">
    <div class="card-title"><h3>Waiting for a decision ({{ $pending->count() }})</h3></div>

    @forelse ($pending as $r)
        <div class="req">
            <strong>#{{ $r->booking->id }} · {{ $r->booking->event_name }}</strong>
            <span style="color:var(--muted)"> — {{ $r->booking->user->name ?? 'Unknown' }}</span>

            <div class="req-grid">
                <div><span>Current</span>{{ $r->booking->event_date->format('M d, Y') }} · {{ $fmt($r->booking->event_time) }}</div>
                <div><span>Requested</span><b>{{ $r->requested_date->format('M d, Y') }} · {{ $fmt($r->requested_time) }}</b></div>
                <div><span>Venue</span>{{ $r->booking->location ?: '—' }}</div>
                <div><span>Reason</span>{{ $r->reason ?: '—' }}</div>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
                <form method="POST" action="{{ route('admin.reschedules.approve', $r->id) }}">
                    @csrf
                    <button class="btn btn-primary btn-small">Approve</button>
                </form>

                <form method="POST" action="{{ route('admin.reschedules.reject', $r->id) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                    @csrf
                    <input type="text" name="admin_note" maxlength="500" placeholder="Note to the customer (optional)" style="min-width:240px">
                    <button class="btn btn-danger btn-small">Reject</button>
                </form>
            </div>
        </div>
    @empty
        <p class="empty">No requests are waiting. 🎉</p>
    @endforelse
</div>

<div class="card">
    <div class="card-title"><h3>Recent decisions</h3></div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Booking</th><th>Customer</th><th>Requested</th><th>Result</th><th>Decided by</th><th>When</th></tr></thead>
            <tbody>
            @forelse ($recent as $r)
                <tr>
                    <td>#{{ $r->booking->id }} {{ $r->booking->event_name }}</td>
                    <td>{{ $r->booking->user->name ?? '—' }}</td>
                    <td>{{ $r->requested_date->format('M d, Y') }} · {{ $fmt($r->requested_time) }}</td>
                    <td><span class="badge badge-{{ $r->status === 'approved' ? 'confirmed' : 'cancelled' }}">{{ $r->status }}</span>
                        @if ($r->admin_note)<br><small style="color:var(--muted)">{{ $r->admin_note }}</small>@endif</td>
                    <td>{{ $r->decider->name ?? '—' }}</td>
                    <td>{{ $r->decided_at?->format('M d, g:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No decisions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
