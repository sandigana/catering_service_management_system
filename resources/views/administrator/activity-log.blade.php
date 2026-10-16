{{-- resources/views/administrator/activity-log.blade.php --}}
@extends('layouts.admin')

@section('title', 'Activity Log')

@section('content')

<div class="page-header">
    <div>
        <h1>Activity Log</h1>
        <p>Who did what and when. Passwords and tokens are never recorded.</p>
    </div>
</div>

<div class="card">
    <form method="GET" action="{{ route('admin.activity-log') }}" class="report-form" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <select name="action">
            <option value="">All actions</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
        </select>

        <select name="subject">
            <option value="">All records</option>
            @foreach ($subjects as $subject)
                <option value="{{ $subject }}" @selected(request('subject') === $subject)>{{ $subject }}</option>
            @endforeach
        </select>

        <button class="btn btn-primary">Filter</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('M d, Y g:i A') }}</td>
                    <td>{{ $log->user->name ?? 'System' }}</td>
                    <td><span class="badge badge-{{ in_array($log->action, ['deleted']) ? 'cancelled' : 'confirmed' }}">{{ $log->action }}</span></td>
                    <td>
                        {{ $log->description }}
                        @if (! empty($log->changes['new']))
                            <br><small style="color:#748079">
                                @foreach ($log->changes['new'] as $field => $value)
                                    {{ $field }}: {{ is_scalar($value) || is_null($value) ? (string) $value : json_encode($value) }}@if (! $loop->last), @endif
                                @endforeach
                            </small>
                        @endif
                    </td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No activity recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $logs->links() }}</div>
</div>

@endsection
