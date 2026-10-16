@extends('layouts.admin')

@section('title', 'Events')

@section('content')

<div class="page-header">

    <div>
        <h1>Events</h1>
        <p>Manage scheduled catering events</p>
    </div>

    <a href="{{ route('admin.events.create') }}" class="btn btn-primary">
        + Add Event
    </a>

</div>

<div class="card">

    <div class="table-header">

        <div>
            <h3>Event List</h3>
            <p>All catering events</p>
        </div>

        <form
            action="{{ route('admin.events.index') }}"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search event or location..."
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
                    <th>Event Name</th>
                    <th>Booking</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            @forelse($events as $event)

                <tr>

                    <td>{{ $event->id }}</td>

                    <td>
                        <strong>{{ $event->event_name }}</strong>
                    </td>

                    <td>
                        #{{ $event->booking_id ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $event->event_date
                            ? \Carbon\Carbon::parse($event->event_date)->format('M d, Y')
                            : 'N/A'
                        }}
                    </td>

                    <td>
                        {{ $event->event_time ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $event->location ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $event->guests ?? 0 }}
                    </td>

                    <td>

                        <span class="badge badge-{{ strtolower($event->status) }}">
                            {{ ucfirst($event->status) }}
                        </span>

                    </td>

                    <td class="actions">

                        <a
                            href="{{ route('admin.events.show', $event->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        <a
                            href="{{ route('admin.events.edit', $event->id) }}"
                            class="btn btn-small btn-success"
                        >
                            Edit
                        </a>

                        <a
                            href="{{ route('admin.events.sheet', $event->id) }}"
                            class="btn btn-small btn-success"
                            target="_blank"
                        >
                            Day sheet
                        </a>

                        <a
                            href="{{ route('admin.events.supplies', $event->id) }}"
                            class="btn btn-small btn-info"
                        >
                            Supplies
                        </a>

                        @if (auth()->user()->isAdmin())
                        <form
                            action="{{ route('admin.events.destroy', $event->id) }}"
                            method="POST"
                            style="display:inline"
                            onsubmit="return confirm('Delete this event?')"
                        >

                            @csrf
                            @method('DELETE')

                            <button class="btn btn-small btn-danger">
                                Delete
                            </button>

                        </form>
                        @endif

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="9" class="empty">
                        No events found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="pagination">{{ $events->links() }}</div>

@endsection