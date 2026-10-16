{{-- resources/views/administrator/event-supplies.blade.php --}}
@extends('layouts.admin')

@section('title', 'Event Supplies')

@section('content')

<div class="page-header">
    <div>
        <h1>Supplies for {{ $event->event_name }}</h1>
        <p>{{ $event->event_date->format('F d, Y') }} · {{ $event->guests }} guests · reserving stock deducts it from inventory.</p>
    </div>

    <a href="{{ route('admin.events.index') }}" class="btn btn-info">← Events</a>
</div>

@if (count($plan))
<div class="card">
    <div class="card-title">
        <h3>From the package recipe ({{ $event->booking->package->name }})</h3>
        <p>Per-guest amounts × {{ $event->guests ?: $event->booking->guests }} guests</p>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Item</th><th>Needed</th><th>Reserved</th><th>Still missing</th><th>In stock</th></tr></thead>
            <tbody>
            @foreach ($plan as $row)
                <tr>
                    <td>{{ $row['item']->name }}</td>
                    <td>{{ $row['needed'] }} {{ $row['item']->unit }}</td>
                    <td>{{ $row['reserved'] }}</td>
                    <td>{{ $row['shortfall'] }}</td>
                    <td>
                        {{ rtrim(rtrim(number_format((float) $row['item']->quantity, 2, '.', ''), '0'), '.') }}
                        @if ($row['shortfall'] > (float) $row['item']->quantity)<span class="badge badge-cancelled">Not enough</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.events.supplies.package', $event->id) }}" style="margin-top:14px">
        @csrf
        @error('package')<div class="field-error" style="margin-bottom:8px">{{ $message }}</div>@enderror
        <button class="btn btn-primary">Reserve what is still missing</button>
        <small style="color:var(--muted);margin-left:8px">All or nothing. Pressing it twice never reserves twice.</small>
    </form>
</div>
@elseif (auth()->user()->isAdmin() && $event->booking?->package)
<div class="card"><p style="color:var(--muted)">This package has no supply recipe yet. <a href="{{ route('admin.packages.supplies', $event->booking->package->id) }}">Set one up</a> to reserve stock automatically.</p></div>
@endif

<div class="card">
    <div class="card-title"><h3>Reserve stock</h3></div>

    <form method="POST" action="{{ route('admin.events.supplies.store', $event->id) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        @csrf

        <div class="form-group">
            <label for="inventory_item_id">Item</label>
            <select id="inventory_item_id" name="inventory_item_id" required>
                <option value="">— Select —</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected((string) old('inventory_item_id') === (string) $item->id)>
                        {{ $item->name }} ({{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }} {{ $item->unit }} left)
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input id="quantity" type="number" name="quantity" step="0.01" min="0.01" value="{{ old('quantity') }}" required>
        </div>

        <button class="btn btn-primary">Reserve</button>
    </form>
</div>

<div class="card">
    <div class="card-title"><h3>Reserved for this event</h3></div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Item</th><th>Quantity</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($event->inventoryItems as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->pivot->quantity, 2, '.', ''), '0'), '.') }} {{ $item->unit }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.events.supplies.destroy', [$event->id, $item->id]) }}"
                              onsubmit="return confirm('Return this stock to inventory?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-small btn-danger">Return to stock</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">Nothing reserved yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
