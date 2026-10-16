@extends('layouts.admin')

@section('title', 'Packages')

@section('content')

<div class="page-header">

    <div>
        <h1>Packages</h1>
        <p>Manage catering packages</p>
    </div>

    <a
        href="{{ route('admin.packages.create') }}"
        class="btn btn-primary"
    >
        + Add Package
    </a>

</div>

<div class="card">

    <div class="table-header">

        <div>
            <h3>Catering Packages</h3>
            <p>Available catering packages</p>
        </div>

        <form
            action="{{ route('admin.packages.index') }}"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search package name..."
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
                    <th>Image</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            @forelse($packages as $package)

                <tr>

                    <td>{{ $package->id }}</td>

                    <td>

                        @if($package->image)

                            <img
                                src="{{ asset('storage/' . $package->image) }}"
                                class="table-image"
                                alt="{{ $package->name }}"
                            >

                        @else

                            <div class="no-image">
                                🍽️
                            </div>

                        @endif

                    </td>

                    <td>
                        <strong>{{ $package->name }}</strong>
                    </td>

                    <td>
                        {{ \Illuminate\Support\Str::limit($package->description, 50) }}
                    </td>

                    <td>
                        ₱{{ number_format($package->price, 2) }}
                    </td>

                    <td>

                        <span class="badge badge-confirmed">
                            {{ ucfirst($package->status) }}
                        </span>

                    </td>

                    <td class="actions">

                        <a
                            href="{{ route('admin.packages.show', $package->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        <a
                            href="{{ route('admin.packages.supplies', $package->id) }}"
                            class="btn btn-small btn-info"
                        >
                            Supplies
                        </a>

                        <a
                            href="{{ route('admin.packages.edit', $package->id) }}"
                            class="btn btn-small btn-success"
                        >
                            Edit
                        </a>

                        <form
                            action="{{ route('admin.packages.destroy', $package->id) }}"
                            method="POST"
                            style="display:inline"
                            onsubmit="return confirm('Delete this package?')"
                        >

                            @csrf
                            @method('DELETE')

                            <button class="btn btn-small btn-danger">
                                Delete
                            </button>

                        </form>

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="7" class="empty">
                        No packages found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="pagination">{{ $packages->links() }}</div>

@endsection