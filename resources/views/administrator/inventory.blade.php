{{-- resources/views/administrator/inventory.blade.php --}}
@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')

<div class="page-header">
    <div>
        <h1>Inventory</h1>
        <p>Ingredients and supplies. {{ $lowCount }} item(s) at or below their low-stock level.</p>
    </div>

    <a href="{{ route('admin.inventory.create') }}" class="btn btn-primary">+ Add Item</a>
</div>

<div class="card">
    <form method="GET" action="{{ route('admin.inventory.index') }}" class="report-form" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search item...">

        <select name="filter">
            <option value="">All items</option>
            <option value="low" @selected(request('filter') === 'low')>Low stock only</option>
        </select>

        <button class="btn btn-primary">Filter</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>In stock</th>
                    <th>Alert at</th>
                    <th>Status</th>
                    <th>Adjust stock</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td><strong>{{ $item->name }}</strong></td>

                    <td>
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }} {{ $item->unit }}
                        @if ($item->isLow())
                            <span class="badge badge-cancelled">Low</span>
                        @endif
                    </td>

                    <td>{{ rtrim(rtrim(number_format((float) $item->low_stock_threshold, 2, '.', ''), '0'), '.') }} {{ $item->unit }}</td>

                    <td><span class="badge badge-{{ $item->status === 'active' ? 'confirmed' : 'cancelled' }}">{{ ucfirst($item->status) }}</span></td>

                    <td>
                        <form method="POST" action="{{ route('admin.inventory.adjust', $item->id) }}" style="display:flex;gap:6px;flex-wrap:wrap;">
                            @csrf
                            <input type="number" name="change" step="0.01" placeholder="+10 / -2" required style="width:90px">
                            <input type="text" name="reason" placeholder="Reason" required maxlength="255" style="width:140px">
                            <button class="btn btn-small btn-info">Apply</button>
                        </form>
                    </td>

                    <td class="actions">
                        <a href="{{ route('admin.inventory.edit', $item->id) }}" class="btn btn-small btn-success">Edit</a>

                        @if (auth()->user()->isAdmin())
                            <form action="{{ route('admin.inventory.destroy', $item->id) }}" method="POST" style="display:inline"
                                  onsubmit="return confirm('Delete this item?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-small btn-danger">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No inventory items found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $items->links() }}</div>
</div>

@endsection
