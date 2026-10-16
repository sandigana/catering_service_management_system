{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - Catering System</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=2">
    @stack('styles')
</head>
<body>
@php
    $me = auth()->user();
    $pendingCount = \App\Models\RescheduleRequest::pending()->count();
    $initials = collect(explode(' ', trim($me->name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
@endphp

<div class="admin-layout">

    <aside class="sidebar">
        <div class="logo">
            <div class="logo-mark">🍳</div>
            <h2>Catering<span>System</span></h2>
        </div>

        <div class="side-profile">
            <div class="side-avatar">{{ $initials ?: '👤' }}</div>
            <strong>{{ $me->name }}</strong>
            <small>{{ $me->isAdmin() ? 'Administrator' : 'Staff' }}</small>
            <div class="side-quick">
                <a href="{{ route('admin.bookings.create') }}" title="New booking">➕</a>
                <a href="{{ route('admin.payments.index') }}" title="Payments">💳</a>
                <a href="{{ route('admin.inventory.index') }}" title="Inventory">🥫</a>
            </div>
        </div>

        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="nav-icon">🏠</span>Home<span class="nav-arrow">›</span>
            </a>
            <a href="{{ route('admin.bookings.index') }}" class="{{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
                <span class="nav-icon">📅</span>Bookings<span class="nav-arrow">›</span>
            </a>
            <a href="{{ route('admin.calendar') }}" class="{{ request()->routeIs('admin.calendar') ? 'active' : '' }}">
                <span class="nav-icon">🗓️</span>Calendar<span class="nav-arrow">›</span>
            </a>

            <a href="{{ route('admin.customers') }}" class="{{ request()->routeIs('admin.customers') ? 'active' : '' }}">
                <span class="nav-icon">👥</span>Customers<span class="nav-arrow">›</span>
            </a>
            <a href="{{ route('admin.reschedules.index') }}" class="{{ request()->routeIs('admin.reschedules.*') ? 'active' : '' }}">
                <span class="nav-icon">🔁</span>Reschedules
                @if ($pendingCount > 0)<span class="nav-badge">{{ $pendingCount }}</span>@endif
                <span class="nav-arrow">›</span>
            </a>

            <a href="{{ route('admin.events.index') }}" class="{{ request()->routeIs('admin.events.*') ? 'active' : '' }}">
                <span class="nav-icon">🎉</span>Events<span class="nav-arrow">›</span>
            </a>
            <a href="{{ route('admin.inventory.index') }}" class="{{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}">
                <span class="nav-icon">🥫</span>Inventory<span class="nav-arrow">›</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                <span class="nav-icon">💳</span>Payments<span class="nav-arrow">›</span>
            </a>
            @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.staff.index') }}" class="{{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
                <span class="nav-icon">👨‍🍳</span>Kitchen Staff<span class="nav-arrow">›</span>
            </a>
            @endif
            @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.menu-items.index') }}" class="{{ request()->routeIs('admin.menu-items.*') ? 'active' : '' }}">
                <span class="nav-icon">🍽️</span>Menu Items<span class="nav-arrow">›</span>
            </a>
            @endif
            @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.packages.index') }}" class="{{ request()->routeIs('admin.packages.*') ? 'active' : '' }}">
                <span class="nav-icon">📦</span>Packages<span class="nav-arrow">›</span>
            </a>
            @endif
            @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
                <span class="nav-icon">📊</span>Analytics & Reports<span class="nav-arrow">›</span>
            </a>
            @endif
            @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <span class="nav-icon">👤</span>Users<span class="nav-arrow">›</span>
            </a>
            @endif
            @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.activity-log') }}" class="{{ request()->routeIs('admin.activity-log') ? 'active' : '' }}">
                <span class="nav-icon">🧾</span>Activity Log<span class="nav-arrow">›</span>
            </a>
            @endif
        </nav>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button type="button" class="menu-button" onclick="document.body.classList.toggle('nav-open')" aria-label="Toggle menu">☰</button>

            <form class="top-search" method="GET" action="{{ route('admin.bookings.index') }}">
                <input type="text" name="search" value="{{ request()->routeIs('admin.bookings.*') ? request('search') : '' }}" placeholder="Search bookings or customers...">
                <button type="submit" aria-label="Search">🔍</button>
            </form>

            <div class="admin-account">
                <div class="avatar">{{ $initials ?: '👤' }}</div>
                <div>
                    <div class="account-name">{{ $me->name }}</div>
                    <div class="account-email">{{ $me->isAdmin() ? 'Admin' : 'Staff' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="logout-btn">Logout</button>
                </form>
            </div>
        </header>

        <section class="content">
            @include('partials.flash')

            @yield('content')
        </section>
    </main>
</div>

@stack('scripts')
</body>
</html>
