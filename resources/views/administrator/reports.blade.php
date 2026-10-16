@extends('layouts.admin')

@section('title', 'Reports')

@section('content')

<div class="page-header">

    <div>
        <h1>Reports</h1>
        <p>View catering service statistics and reports</p>
    </div>

</div>

{{-- Summary Cards --}}

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-icon green">
            ₱
        </div>

        <div>
            <span>Total Revenue</span>

            <h2>
                ₱{{ number_format($totalRevenue ?? 0, 2) }}
            </h2>
        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon blue">
            📋
        </div>

        <div>
            <span>Total Bookings</span>

            <h2>
                {{ $totalBookings ?? 0 }}
            </h2>
        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon purple">
            👥
        </div>

        <div>
            <span>Total Customers</span>

            <h2>
                {{ $totalCustomers ?? 0 }}
            </h2>
        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon orange">
            💳
        </div>

        <div>
            <span>Total Payments</span>

            <h2>
                {{ $totalPayments ?? 0 }}
            </h2>
        </div>

    </div>

</div>

{{-- Date Filter --}}

<div class="card">

    <div class="card-title">
        <h3>Generate Report</h3>
        <p>Select a date range for your report.</p>
    </div>

    <form
        action="{{ route('admin.reports') }}"
        method="GET"
        class="report-form"
    >

        <div class="form-group">

            <label>From</label>

            <input
                type="date"
                name="from"
                value="{{ request('from', $from->toDateString()) }}"
            >

        </div>

        <div class="form-group">

            <label>To</label>

            <input
                type="date"
                name="to"
                value="{{ request('to', $to->toDateString()) }}"
            >

        </div>

        <div class="form-group">

            <label>Group by</label>

            <select name="period">
                @foreach (['daily' => 'Day', 'weekly' => 'Week', 'monthly' => 'Month'] as $key => $label)
                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                @endforeach
            </select>

        </div>

        <div class="form-group button-group">

            <button class="btn btn-primary">
                Generate Report
            </button>

            <a
                href="{{ route('admin.reports.export', request()->only('from', 'to', 'period')) }}"
                class="btn btn-success"
            >
                Export CSV
            </a>

            <button type="button" class="btn btn-info" onclick="window.print()">
                Print / PDF
            </button>

        </div>

    </form>

</div>

{{-- Period summaries --}}

<div class="report-grid">

    <div class="card">
        <div class="card-title">
            <h3>Bookings per {{ $period === 'daily' ? 'day' : ($period === 'weekly' ? 'week' : 'month') }}</h3>
            <p>By event date, {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }}</p>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Period</th><th>Bookings</th><th>Booked value</th></tr></thead>
                <tbody>
                @forelse ($bookingSeries as $label => $row)
                    <tr><td>{{ $label }}</td><td>{{ $row['count'] }}</td><td>₱{{ number_format((float) $row['value'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">No bookings in this range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title">
            <h3>Sales per {{ $period === 'daily' ? 'day' : ($period === 'weekly' ? 'week' : 'month') }}</h3>
            <p>Money received · outstanding on live bookings: ₱{{ number_format($outstanding, 2) }}</p>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Period</th><th>Payments</th><th>Received</th></tr></thead>
                <tbody>
                @forelse ($salesSeries as $label => $row)
                    <tr><td>{{ $label }}</td><td>{{ $row['count'] }}</td><td>₱{{ number_format((float) $row['value'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">No payments in this range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="card">
    <div class="card-title">
        <h3>Top customers</h3>
        <p>Ranked by money paid</p>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Bookings</th><th>Paid</th></tr></thead>
            <tbody>
            @forelse ($topCustomers as $customer)
                <tr><td>{{ $customer->name }}</td><td>{{ $customer->bookings_count }}</td><td>₱{{ number_format($customer->paid_total, 2) }}</td></tr>
            @empty
                <tr><td colspan="3" class="empty">No customers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Report Tables --}}

<div class="report-grid">

    <div class="card">

        <div class="card-title">
            <h3>Recent Bookings</h3>
        </div>

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($recentBookings ?? [] as $booking)

                    <tr>

                        <td>
                            {{ $booking->user->name ?? 'Unknown' }}
                        </td>

                        <td>
                            {{ $booking->package->name ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $booking->event_date?->format('M d, Y') ?? 'N/A' }}
                        </td>

                        <td>

                            <span class="badge badge-{{ strtolower($booking->status) }}">
                                {{ ucfirst($booking->status) }}
                            </span>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="4" class="empty">
                            No booking records.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

    <div class="card">

        <div class="card-title">
            <h3>Top Packages</h3>
        </div>

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($topPackages ?? [] as $package)

                    <tr>

                        <td>
                            {{ $package->name ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $package->bookings_count ?? 0 }}
                        </td>

                        <td>
                            ₱{{ number_format($package->revenue ?? 0, 2) }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="3" class="empty">
                            No package data.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection