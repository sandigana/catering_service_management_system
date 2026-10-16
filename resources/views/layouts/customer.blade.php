{{-- resources/views/layouts/customer.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My Bookings') - Catering System</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('css/customer.css') }}?v=2">
    @stack('styles')
</head>
<body>
@php
    $me = auth()->user();
    $initials = collect(explode(' ', trim($me->name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
@endphp

<div class="customer-layout">

    <aside class="customer-sidebar">
        <div class="customer-logo">🍴 catering.<small>Good food, great moments</small></div>

        <div class="nav-label">My Panel</div>
        <nav class="customer-nav">
            <a href="{{ route('customer.dashboard') }}" class="{{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
                <span class="customer-icon">🏠</span>Overview
            </a>
            <a href="{{ route('customer.bookings.index') }}" class="{{ request()->routeIs('customer.bookings.*') ? 'active' : '' }}">
                <span class="customer-icon">📅</span>Bookings
            </a>
            <a href="{{ route('customer.packages') }}" class="{{ request()->routeIs('customer.packages') ? 'active' : '' }}">
                <span class="customer-icon">📦</span>Packages
            </a>
            <a href="{{ route('customer.profile') }}" class="{{ request()->routeIs('customer.profile*') ? 'active' : '' }}">
                <span class="customer-icon">👤</span>Profile
            </a>
        </nav>

        <div class="side-user">
            <div class="customer-avatar">{{ $initials ?: '👤' }}</div>
            <div>
                <strong>{{ $me->name }}</strong>
                <span>Customer</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button title="Logout" aria-label="Logout">⇥</button>
            </form>
        </div>
    </aside>

    <main class="customer-main">
        <div class="customer-topbar">
            <button type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="Menu">☰</button>
            <strong>🍴 catering.</strong>
        </div>

        <section class="customer-content">
            @include('partials.flash')

            @yield('content')
        </section>
    </main>
</div>

@stack('scripts')
</body>
</html>
