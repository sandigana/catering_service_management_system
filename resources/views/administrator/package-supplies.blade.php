{{-- resources/views/administrator/package-supplies.blade.php --}}
@extends('layouts.admin')

@section('title', 'Package supplies')

@section('content')

<div class="page-header">
    <div>
        <h1>Supplies for "{{ $package->name }}"</h1>
        <p>How much stock one guest uses. For an event, the system multiplies this by the number of guests and reserves it in one click.</p>
    </div>

    <a href="{{ route('admin.packages.index') }}" class="btn btn-info">← Packages</a>
</div>

<div class="card">
    <div class="card-title"><h3>Add or update an item</h3></div>

    <form method="POST" action="{{ route('admin.packages.supplies.store', $package->id) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        @csrf

        <div class="form-group">
            <label for="inventory_item_id">Inventory item</label>
            <select id="inventory_item_id" name="inventory_item_id" required>
                <option value="">— Select —</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected((string) old('inventory_item_id') === (string) $item->id)>{{ $item->name }} ({{ $item->unit }})</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="quantity_per_guest">Quantity per guest</label>
            <input id="quantity_per_guest" type="number" name="quantity_per_guest" step="0.001" min="0.001" value="{{ old('quantity_per_guest') }}" placeholder="e.g. 0.25" required>
        </div>

        <button class="btn btn-primary">Save</button>
    </form>
</div>

<div class="card">
    <div class="card-title"><h3>Recipe</h3></div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Item</th><th>Per guest</th><th>For 50 guests</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($package->supplies as $supply)
                <tr>
                    <td>{{ $supply->item->name }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $supply->quantity_per_guest, 3, '.', ''), '0'), '.') }} {{ $supply->item->unit }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $supply->quantity_per_guest * 50, 2, '.', ''), '0'), '.') }} {{ $supply->item->unit }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.packages.supplies.destroy', [$package->id, $supply->id]) }}" onsubmit="return confirm('Remove this item?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-small btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No items yet. Add what one guest of this package consumes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
