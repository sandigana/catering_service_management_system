@extends('layouts.customer')

@section('title', 'New Booking')

@section('content')

<div class="c-head">
    <div>
        <h1>Book an Event</h1>
        <p>Tell us about your event. We'll confirm availability and the final price.</p>
    </div>
    <a class="c-btn alt" href="{{ route('customer.bookings.index') }}">← My Bookings</a>
</div>

<div class="c-card">
    <form action="{{ route('customer.bookings.store') }}" method="POST" class="c-form">
        @csrf

        <div class="full">
            <label for="package_id">Package</label>
            <select id="package_id" name="package_id" required>
                <option value="">— Select a package —</option>
                @foreach ($packages as $package)
                    <option value="{{ $package->id }}" @selected((string) old('package_id', $selected) === (string) $package->id)>
                        {{ $package->name }} — ₱{{ number_format($package->price, 2) }} per guest
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="event_name">Event name</label>
            <input id="event_name" type="text" name="event_name" value="{{ old('event_name') }}" required>
        </div>

        <div>
            <label for="event_date">Event date</label>
            <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}" min="{{ now()->toDateString() }}" required>
            <div id="availability" class="hint" aria-live="polite"></div>
        </div>

        <div>
            <label for="event_time">Start time</label>
            <input id="event_time" type="time" name="event_time" value="{{ old('event_time') }}" required>
        </div>

        <div>
            <label for="guests">Number of guests</label>
            <input id="guests" type="number" name="guests" value="{{ old('guests', 50) }}" min="1" required>
        </div>

        <div>
            <label for="location">Venue / location</label>
            <input id="location" type="text" name="location" value="{{ old('location') }}" required>
        </div>

        <div class="full">
            <label for="notes">Notes (allergies, theme, special requests)</label>
            <textarea id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
        </div>

        <div class="full">
            <button type="submit" class="c-btn">Submit booking request</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
    // Asks the server whether the chosen date/time/venue is still free (same rules as the real booking check).
    (function () {
        var url = @json(route('customer.availability'));
        var box = document.getElementById('availability');
        var fields = ['event_date', 'event_time', 'location'].map(function (id) { return document.getElementById(id); });
        var timer;

        function render(ok, text, extra) {
            box.className = 'hint ' + (ok ? 'ok' : 'bad');
            box.textContent = (ok ? '✓ ' : '✗ ') + text + (extra || '');
        }

        function check() {
            var date = fields[0].value;
            if (!date) { box.className = 'hint'; box.textContent = ''; return; }

            var params = new URLSearchParams({ date: date });
            if (fields[1].value) params.set('time', fields[1].value.slice(0, 5));
            if (fields[2].value.trim()) params.set('location', fields[2].value.trim());

            fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (d) {
                    var extra = d.available ? ' (' + d.remaining + ' slot' + (d.remaining === 1 ? '' : 's') + ' left that day)' : '';
                    if (d.full_dates.length) extra += ' · Fully booked this month: ' + d.full_dates.join(', ');
                    render(d.available, d.message, extra);
                })
                .catch(function () { box.className = 'hint'; box.textContent = ''; });
        }

        fields.forEach(function (f) {
            f.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 250); });
        });
        if (fields[0].value) check();
    })();
</script>
@endpush
