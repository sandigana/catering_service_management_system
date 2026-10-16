@extends('layouts.customer')

@section('title', 'Packages')

@section('content')

<div class="c-head">
    <div>
        <h1>Catering Packages</h1>
        <p>Choose a package for your next event</p>
    </div>
</div>

<div class="c-grid">
    @forelse ($packages as $package)
        <div class="c-card c-pkg">
            @if ($package->image)
                <img src="{{ asset('storage/' . $package->image) }}" alt="{{ $package->name }}">
            @endif

            <h3>{{ $package->name }}</h3>
            <p>{{ $package->description ?? 'Perfect for your special event.' }}</p>
            <p><strong>₱{{ number_format($package->price, 2) }}</strong> per guest</p>

            @if ($package->menuItems->isNotEmpty())
                <p style="font-size:13px;color:#666;">Includes: {{ $package->menuItems->pluck('name')->implode(', ') }}</p>
            @endif

            <a class="c-btn" href="{{ route('customer.bookings.create', ['package' => $package->id]) }}">Book this package</a>
        </div>
    @empty
        <div class="c-card">No packages are available right now. Please check back soon.</div>
    @endforelse
</div>

@endsection
