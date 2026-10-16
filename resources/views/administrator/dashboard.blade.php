{{-- resources/views/administrator/dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Home')

@php
    // Small helpers kept in the view because they only format numbers for display.
    $deltaClass = fn ($d, $goodWhenUp = true) => $d == 0 ? 'flat' : (($d > 0) === $goodWhenUp ? 'up' : 'down');
    $deltaText = fn ($d) => ($d > 0 ? '▲ ' : ($d < 0 ? '▼ ' : '– ')) . abs($d) . '%';
    $fmtTime = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('g:i A') : '—';

    $splitTotal = max(1, array_sum($paymentSplit));
    $paidDeg = round($paymentSplit['paid'] / $splitTotal * 360);
    $partialDeg = $paidDeg + round($paymentSplit['partial'] / $splitTotal * 360);
    $maxDaily = max(5, $dailySeries->max('count'));
    $maxStatus = max(1, $statusCounts->max());
@endphp

@section('content')

<div class="dashboard-heading">
    <div>
        <h1>Welcome back, {{ strtok(auth()->user()->name, ' ') }}</h1>
        <p>Here is what is happening with your catering business.</p>
    </div>
    <div class="today">📅 {{ now()->format('l, F d, Y') }}</div>
</div>

{{-- KPI row --}}
<div class="kpis">
    <div class="kpi">
        <div>
            <small>Accepted Bookings <span style="font-weight:400">· this month</span></small>
            <strong>{{ number_format($kpi['accepted']['value']) }}</strong>
            <span class="delta {{ $deltaClass($kpi['accepted']['delta']) }}">{{ $deltaText($kpi['accepted']['delta']) }}</span>
        </div>
        <div class="kpi-art">✅</div>
    </div>

    <div class="kpi">
        <div>
            <small>Cancelled Bookings <span style="font-weight:400">· this month</span></small>
            <strong>{{ str_pad($kpi['cancelled']['value'], 2, '0', STR_PAD_LEFT) }}</strong>
            <span class="delta {{ $deltaClass($kpi['cancelled']['delta'], false) }}">{{ $deltaText($kpi['cancelled']['delta']) }}</span>
        </div>
        <div class="kpi-art">🚫</div>
    </div>

    <div class="kpi">
        <div>
            <small>Lost Revenue <span style="font-weight:400">· this month</span></small>
            <strong>₱{{ number_format($kpi['lost']['value'], 2) }}</strong>
            <span class="delta {{ $deltaClass($kpi['lost']['delta'], false) }}">{{ $deltaText($kpi['lost']['delta']) }}</span>
        </div>
        <div class="kpi-art">💸</div>
    </div>
</div>

<div class="dash-grid">

    {{-- LEFT --}}
    <div class="dash-left">

        <div class="pair">
            @foreach ([['New Bookings', 'pending', $newBookings, 'new'], ['Upcoming Bookings', 'confirmed', $upcomingBookings, 'upc']] as [$title, $kind, $list, $key])
                <div class="dash-card" data-carousel="{{ $key }}">
                    <h3>🗓️ {{ $title }}
                        <a class="eye" href="{{ route('admin.bookings.index', ['status' => $kind]) }}" title="View all">👁</a>
                    </h3>

                    @forelse ($list as $i => $b)
                        <div class="slide {{ $i === 0 ? 'on' : '' }} mini">
                            <label>Guest Name:</label><b>{{ $b->user->name ?? 'Unknown' }}</b>
                            <label>Package:</label><b>{{ $b->package->name ?? 'No package' }}</b>
                            <div class="dt">
                                <div><label>Date:</label><b>{{ $b->event_date->format('d F, Y') }}</b></div>
                                <div><label>Time:</label><b>{{ $fmtTime($b->event_time) }}</b></div>
                            </div>
                            <div style="margin-top:12px">
                                <a class="btn btn-small btn-info" href="{{ route('admin.bookings.show', $b->id) }}">Open #{{ $b->id }}</a>
                            </div>
                        </div>
                    @empty
                        <p style="color:var(--muted);padding:20px 0">Nothing here right now.</p>
                    @endforelse

                    @if ($list->count() > 1)
                        <div class="carousel-nav">
                            <button type="button" data-dir="-1">‹</button>
                            <span data-pos>01/{{ str_pad($list->count(), 2, '0', STR_PAD_LEFT) }}</span>
                            <button type="button" data-dir="1">›</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="dash-card">
            <h3>📈 Analytics and Reports <small style="margin-left:auto;font-weight:400;color:var(--muted)">Bookings received, last 7 days</small></h3>

            <div class="chart" style="margin-top:22px">
                <div class="chart-y">
                    @foreach ([1, .75, .5, .25, 0] as $f)
                        <span>{{ round($maxDaily * $f) }}</span>
                    @endforeach
                </div>
                @foreach ($dailySeries as $d)
                    <div class="bar" style="height: {{ max(1, round($d['count'] / $maxDaily * 100)) }}%" title="{{ $d['count'] }} on {{ $d['label'] }}">
                        @if ($d['count'])<em>{{ $d['count'] }}</em>@endif
                    </div>
                @endforeach
            </div>
            <div class="chart-x">
                @foreach ($dailySeries as $d)<span>{{ $d['label'] }}</span>@endforeach
            </div>
        </div>

        <div class="dash-card">
            <h3>🔗 Quick links</h3>
            <div class="quick">
                <a href="{{ route('admin.customers') }}">👥 <div>{{ $users }}<small>Customers</small></div></a>
                <a href="{{ route('admin.events.index') }}">🎉 <div>{{ $events }}<small>Events</small></div></a>
                <a href="{{ route('admin.payments.index') }}">💳 <div>{{ $payments }}<small>Payments</small></div></a>
                <a href="{{ route('admin.inventory.index', ['filter' => 'low']) }}">🥫 <div>{{ $lowStock }}<small>Low-stock items</small></div></a>
                <a href="{{ route('admin.reschedules.index') }}">🔁 <div>{{ $pendingReschedules }}<small>Reschedule requests</small></div></a>
                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}">🧾 <div>{{ $proofsToVerify }}<small>Payment proofs to verify</small></div></a>
                <a href="{{ route('admin.calendar') }}">🗓️ <div>Open<small>Booking calendar</small></div></a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.packages.index') }}">📦 <div>{{ $packages }}<small>Packages</small></div></a>
                    <a href="{{ route('admin.menu-items.index') }}">🍽️ <div>{{ $menuItems }}<small>Menu items</small></div></a>
                @endif
            </div>
        </div>
    </div>

    {{-- RIGHT --}}
    <div class="dash-right">

        <div class="dash-card">
            <h3>💳 Payment Status</h3>
            <div class="donut" style="background:conic-gradient(var(--brand) 0 {{ $paidDeg }}deg, var(--peach) {{ $paidDeg }}deg {{ $partialDeg }}deg, #ececec {{ $partialDeg }}deg 360deg)"></div>
            <div class="legend">
                <span><i style="background:var(--brand)"></i>Paid ({{ $paymentSplit['paid'] }})</span>
                <span><i style="background:var(--peach)"></i>Partial ({{ $paymentSplit['partial'] }})</span>
                <span><i style="background:#ececec"></i>Unpaid ({{ $paymentSplit['unpaid'] }})</span>
            </div>
        </div>

        <div class="dash-card">
            <h3>📊 Booking Status</h3>
            <div class="hbars">
                @foreach ($statusCounts as $status => $count)
                    <div class="hbar">
                        <span>{{ ucfirst($status) }}</span>
                        <div><i style="width: {{ round($count / $maxStatus * 100) }}%"></i></div>
                        <b>{{ $count }}</b>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="dash-card">
            <h3>🕒 Latest activity</h3>
            @forelse ($recentBookings as $b)
                <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #f3f3f3;font-size:13px">
                    <span>{{ $b->event_name }}<br><small style="color:var(--muted)">{{ $b->user->name ?? 'Unknown' }}</small></span>
                    <span class="badge badge-{{ strtolower($b->status) }}" style="align-self:center">{{ $b->status }}</span>
                </div>
            @empty
                <p style="color:var(--muted)">No bookings yet.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Prev/next buttons for the "New" and "Upcoming" booking cards.
    document.querySelectorAll('[data-carousel]').forEach(function (card) {
        var slides = card.querySelectorAll('.slide');
        var pos = card.querySelector('[data-pos]');
        var index = 0;

        card.querySelectorAll('[data-dir]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                slides[index].classList.remove('on');
                index = (index + Number(btn.dataset.dir) + slides.length) % slides.length;
                slides[index].classList.add('on');
                pos.textContent = String(index + 1).padStart(2, '0') + '/' + String(slides.length).padStart(2, '0');
            });
        });
    });
</script>
@endpush
