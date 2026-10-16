@extends('layouts.admin')

@section('title', 'Users')

@section('content')

<div class="page-header">

    <div>
        <h1>Users</h1>
        <p>Manage administrator and customer accounts</p>
    </div>

    <a
        href="{{ route('admin.users.create') }}"
        class="btn btn-primary"
    >
        + Add User
    </a>

</div>

<div class="card">

    <div class="table-header">

        <div>
            <h3>User Accounts</h3>
            <p>Manage system users and roles</p>
        </div>

        <form
            action="{{ route('admin.users.index') }}"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by name or email..."
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
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            @forelse($users as $user)

                <tr>

                    <td>{{ $user->id }}</td>

                    <td>
                        <strong>{{ $user->name }}</strong>
                    </td>

                    <td>
                        {{ $user->email }}
                    </td>

                    <td>

                        @if($user->role === 'admin')

                            <span class="badge badge-admin">
                                Admin
                            </span>

                        @elseif($user->role === 'staff')

                            <span class="badge badge-confirmed">
                                Staff
                            </span>

                        @else

                            <span class="badge badge-user">
                                User
                            </span>

                        @endif

                    </td>

                    <td>

                        @if ($user->is_active)
                            <span class="badge badge-confirmed">
                                Active
                            </span>
                        @else
                            <span class="badge badge-cancelled">
                                Deactivated
                            </span>
                        @endif

                    </td>

                    <td>
                        {{ $user->created_at
                            ? $user->created_at->format('M d, Y')
                            : 'N/A'
                        }}
                    </td>

                    <td class="actions">

                        <a
                            href="{{ route('admin.users.show', $user->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        <a
                            href="{{ route('admin.users.edit', $user->id) }}"
                            class="btn btn-small btn-success"
                        >
                            Edit
                        </a>

                        @if(auth()->id() !== $user->id)

                            <form
                                action="{{ route('admin.users.destroy', $user->id) }}"
                                method="POST"
                                style="display:inline"
                                onsubmit="return confirm('Delete this user?')"
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

                    <td colspan="7" class="empty">
                        No users found.
                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if(method_exists($users, 'links'))

        <div class="pagination">
            {{ $users->links() }}
        </div>

    @endif

</div>

@endsection