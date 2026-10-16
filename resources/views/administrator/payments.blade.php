@extends('layouts.admin')

@section('title', 'Payments')

@section('content')

<div class="page-header">

    <div>
        <h1>Payments</h1>
        <p>Manage customer payments</p>
    </div>

</div>

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-icon green">
            ₱
        </div>

        <div>
            <span>Total Payments</span>

            <h2>
                ₱{{ number_format($stats['total'], 2) }}
            </h2>
        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon green">
            ✓
        </div>

        <div>
            <span>Paid</span>

            <h2>
                {{ $stats['paid'] }}
            </h2>
        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon orange">
            ⏳
        </div>

        <div>
            <span>Pending</span>

            <h2>
                {{ $stats['pending'] }}
            </h2>
        </div>

    </div>

</div>

<div class="card">

    <div class="table-header">

        <div>
            <h3>Payment Records</h3>
            <p>All payment transactions</p>
        </div>

        <form
            action="{{ route('admin.payments.index') }}"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search reference or customer..."
            >

            <button class="btn btn-primary">
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
                    <th>Booking</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            @forelse($payments as $payment)

                <tr>

                    <td>{{ $payment->id }}</td>

                    <td>
                        {{ $payment->user->name ?? 'Unknown' }}
                    </td>

                    <td>
                        #{{ $payment->booking_id }}
                    </td>

                    <td>
                        ₱{{ number_format($payment->amount, 2) }}
                    </td>

                    <td>
                        {{ $payment->payment_method ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $payment->payment_date
                            ? \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y')
                            : 'N/A'
                        }}
                    </td>

                    <td>

                        <span class="badge badge-{{ strtolower($payment->status) }}">
                            {{ ucfirst($payment->status) }}
                        </span>

                    </td>

                    <td>

                        <a
                            href="{{ route('admin.payments.show', $payment->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        @if ($payment->proof_path)
                            <a
                                href="{{ route('proofs.show', $payment->id) }}"
                                class="btn btn-small btn-info"
                                target="_blank"
                            >
                                Proof
                            </a>
                        @endif

                        @if ($payment->status === 'paid')
                            <a
                                href="{{ route('receipts.pdf', $payment->id) }}"
                                class="btn btn-small btn-info"
                                target="_blank"
                            >
                                PDF
                            </a>

                            <a
                                href="{{ route('receipts.show', $payment->id) }}"
                                class="btn btn-small btn-success"
                                target="_blank"
                            >
                                Receipt
                            </a>
                        @endif

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="8" class="empty">
                        No payment records found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="pagination">{{ $payments->links() }}</div>

@endsection