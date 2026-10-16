@extends('layouts.admin')

@section('title', 'Bookings')

@section('content')

<div class="page-header">
    <div>
        <h1>Bookings</h1>
        <p>Manage all customer bookings</p>
    </div>

    <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary">
        + Add Booking
    </a>
</div>

{{-- Statistics --}}
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div>
            <span>Total Bookings</span>
            <h2>{{ $stats->sum() }}</h2>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">⏳</div>
        <div>
            <span>Pending</span>
            <h2>
                {{ $stats->get('pending', 0) }}
            </h2>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">✓</div>
        <div>
            <span>Confirmed</span>
            <h2>
                {{ $stats->get('confirmed', 0) }}
            </h2>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">✓</div>
        <div>
            <span>Completed</span>
            <h2>
                {{ $stats->get('completed', 0) }}
            </h2>
        </div>
    </div>

</div>

<div class="card">

    <div class="table-header">
        <div>
            <h3>All Bookings</h3>
            <p>Customer booking records</p>
        </div>

        <form action="{{ route('admin.bookings.index') }}" method="GET" class="search-form">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search customer or package..."
            >

            <select name="status">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>
                    Pending
                </option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>
                    Confirmed
                </option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>
                    Completed
                </option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>
                    Cancelled
                </option>
            </select>

            <button type="submit" class="btn btn-primary">
                Search
            </button>
        </form>
    </div>

    <div class="table-responsive">

        <table class="data-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Package</th>
                    <th>Event Date</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            @forelse($bookings as $booking)

                <tr>

                    <td>{{ $booking->id }}</td>

                    <td>
                        <strong>
                            {{ $booking->user->name ?? 'Unknown Customer' }}
                        </strong>
                    </td>

                    <td>
                        {{ $booking->package->name ?? 'No Package' }}
                    </td>

                    <td>
                        {{ $booking->event_date
                            ? \Carbon\Carbon::parse($booking->event_date)->format('M d, Y')
                            : 'N/A'
                        }}
                    </td>

                    <td>{{ $booking->guests ?? 0 }}</td>

                    <td>
                        ₱{{ number_format($booking->total_amount ?? 0, 2) }}
                    </td>

                    <td>₱{{ number_format((float) $booking->amount_paid, 2) }}</td>

                    <td>
                        ₱{{ number_format((float) $booking->balance, 2) }}
                        <span class="badge badge-{{ $booking->payment_status === 'paid' ? 'confirmed' : ($booking->payment_status === 'partial' ? 'pending' : 'cancelled') }}">{{ ucfirst($booking->payment_status) }}</span>
                    </td>

                    <td>
                        <span class="badge badge-{{ strtolower($booking->status) }}">
                            {{ ucfirst($booking->status) }}
                        </span>
                    </td>

                    <td class="actions">

                        <a
                            href="{{ route('admin.bookings.show', $booking->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        <a
                            href="{{ route('admin.bookings.edit', $booking->id) }}"
                            class="btn btn-small btn-success"
                        >
                            Edit
                        </a>

                        @if (auth()->user()->isAdmin())
                        <form
                            action="{{ route('admin.bookings.destroy', $booking->id) }}"
                            method="POST"
                            style="display:inline"
                            onsubmit="return confirm('Delete this booking?')"
                        >
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="btn btn-small btn-danger">
                                Delete
                            </button>
                        </form>
                        @endif

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="10" class="empty">
                        No bookings found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if(method_exists($bookings, 'links'))
        <div class="pagination">
            {{ $bookings->links() }}
        </div>
    @endif

</div>

@endsection