@extends('layouts.admin')

@section('title', 'Menu Items')

@section('content')

<div class="page-header">

    <div>
        <h1>Menu Items</h1>
        <p>Manage catering food and menu items</p>
    </div>

    <a
        href="{{ route('admin.menu-items.create') }}"
        class="btn btn-primary"
    >
        + Add Menu Item
    </a>

</div>

<div class="card">

    <div class="table-header">

        <div>
            <h3>Menu Inventory</h3>
            <p>Available food and catering items</p>
        </div>

        <form
            action="{{ route('admin.menu-items.index') }}"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by name..."
            >

            <select name="category">

                <option value="">All Categories</option>

                <option value="Main Course"
                    {{ request('category') == 'Main Course' ? 'selected' : '' }}>
                    Main Course
                </option>

                <option value="Salad"
                    {{ request('category') == 'Salad' ? 'selected' : '' }}>
                    Salad
                </option>

                <option value="Dessert"
                    {{ request('category') == 'Dessert' ? 'selected' : '' }}>
                    Dessert
                </option>

                <option value="Beverage"
                    {{ request('category') == 'Beverage' ? 'selected' : '' }}>
                    Beverage
                </option>

            </select>

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
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            @forelse($menuItems as $item)

                <tr>

                    <td>{{ $item->id }}</td>

                    <td>

                        @if($item->image)

                            <img
                                src="{{ asset('storage/' . $item->image) }}"
                                class="table-image"
                                alt="{{ $item->name }}"
                            >

                        @else

                            <div class="no-image">
                                🍽️
                            </div>

                        @endif

                    </td>

                    <td>
                        <strong>{{ $item->name }}</strong>
                    </td>

                    <td>
                        {{ $item->category }}
                    </td>

                    <td>
                        ₱{{ number_format($item->price, 2) }}
                    </td>

                    <td>

                        <span class="badge badge-confirmed">
                            {{ ucfirst($item->status) }}
                        </span>

                    </td>

                    <td class="actions">

                        <a
                            href="{{ route('admin.menu-items.show', $item->id) }}"
                            class="btn btn-small btn-info"
                        >
                            View
                        </a>

                        <a
                            href="{{ route('admin.menu-items.edit', $item->id) }}"
                            class="btn btn-small btn-success"
                        >
                            Edit
                        </a>

                        <form
                            action="{{ route('admin.menu-items.destroy', $item->id) }}"
                            method="POST"
                            style="display:inline"
                            onsubmit="return confirm('Delete this menu item?')"
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
                        No menu items found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="pagination">{{ $menuItems->links() }}</div>

@endsection