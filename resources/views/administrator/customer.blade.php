@extends('layouts.admin')

@section('title', 'Customers')

@section('content')

<div class="page-header">

    <div>
        <h1>Customers</h1>
        <p>Manage registered customers</p>
    </div>

</div>

<div class="card">

    <div class="table-header">

        <div>
            <h3>Customer List</h3>
            <p>All customer accounts</p>
        </div>

        <form
            action="{{ route('admin.customers') }}"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by name or email..."
            >

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
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Bookings</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            @forelse($customers as $customer)

                <tr>

                    <td>{{ $customer->id }}</td>

                    <td>
                        <strong>{{ $customer->name }}</strong>
                    </td>

                    <td>{{ $customer->email }}</td>

                    <td>{{ $customer->phone ?: '—' }}</td>

                    <td>
                        {{ $customer->bookings_count }}
                    </td>

                    <td>
                        <span class="badge badge-user">
                            User
                        </span>
                    </td>

                    <td>
                        <span class="badge {{ $customer->is_active ? 'badge-confirmed' : 'badge-cancelled' }}">
                            {{ $customer->is_active ? 'Active' : 'Deactivated' }}
                        </span>
                    </td>

                    <td class="actions">

                        <a
                            href="{{ route('admin.customers.show', $customer->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        <a
                            href="{{ route('admin.customers.edit', $customer->id) }}"
                            class="btn btn-small btn-success"
                        >
                            Edit
                        </a>

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="8" class="empty">
                        No customers found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if(method_exists($customers, 'links'))
        <div class="pagination">
            {{ $customers->links() }}
        </div>
    @endif

</div>

@endsection