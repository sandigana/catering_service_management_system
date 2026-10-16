@extends('layouts.admin')

@section('title', 'Staff')

@section('content')

<div class="page-header">
    <div>
        <h1>Staff</h1>
        <p>Catering team members available for events</p>
    </div>
    <a href="{{ route('admin.staff.create') }}" class="btn btn-primary">+ Add Staff</a>
</div>

<div class="card">

    <div class="table-header">
        <div>
            <h3>Staff List</h3>
            <p>All team members</p>
        </div>

        <form action="{{ route('admin.staff.index') }}" method="GET">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or position">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Position</th><th>Email</th><th>Phone</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($staff as $member)
                <tr>
                    <td>{{ $member->id }}</td>
                    <td><strong>{{ $member->name }}</strong></td>
                    <td>{{ $member->position ?? '—' }}</td>
                    <td>{{ $member->email ?? '—' }}</td>
                    <td>{{ $member->phone ?? '—' }}</td>
                    <td><span class="badge badge-{{ strtolower($member->status) }}">{{ ucfirst($member->status) }}</span></td>
                    <td class="actions">
                        <a href="{{ route('admin.staff.show', $member->id) }}" class="btn btn-small btn-info">View</a>
                        <a href="{{ route('admin.staff.edit', $member->id) }}" class="btn btn-small btn-success">Edit</a>
                        <form action="{{ route('admin.staff.destroy', $member->id) }}" method="POST" style="display:inline"
                              onsubmit="return confirm('Delete this staff member?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-small btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No staff found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

<div class="pagination">{{ $staff->links() }}</div>

@endsection
