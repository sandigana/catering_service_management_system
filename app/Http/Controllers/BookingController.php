<?php
// app/Http/Controllers/BookingController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Services\BookingAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Serves both areas: admin.bookings.* (full CRUD through CrudController) and
 * customer.bookings.* (own bookings only: index, create, store, show, cancel).
 */
class BookingController extends CrudController
{
    private const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function __construct(private BookingAvailability $availability)
    {
    }

    private function isAdminArea(): bool
    {
        return request()->routeIs('admin.*');
    }

    protected function model(): string { return Booking::class; }
    protected function routeBase(): string { return 'admin.bookings'; }
    protected function label(): string { return 'Booking'; }

    /* ---------------- list ---------------- */

    public function index(Request $request)
    {
        if (! $this->isAdminArea()) {
            $bookings = Booking::with('package')->withPaid()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(10);

            return view('customer.booking', compact('bookings'));
        }

        $bookings = Booking::with(['user', 'package'])->withPaid()
            ->when($request->search, fn ($q, $s) => $q->where(
                fn ($w) => $w->where('event_name', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            ))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Counts for the cards cover ALL bookings, not just the 10 rows on this page.
        $stats = Booking::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('administrator.booking', compact('bookings', 'stats'));
    }

    /* ---------------- customer-side create / store / show / cancel ---------------- */

    public function create()
    {
        if ($this->isAdminArea()) {
            return parent::create();
        }

        $packages = Package::where('status', 'active')->orderBy('name')->get();

        return view('customer.booking-form', [
            'packages' => $packages,
            'selected' => request('package'),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->isAdminArea()) {
            return DB::transaction(fn () => parent::store($request));
        }

        $data = $request->validate([
            'package_id' => ['required', Rule::exists('packages', 'id')->where('status', 'active')],
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', 'date_format:H:i,H:i:s'],
            'guests' => ['required', 'integer', 'min:1', 'max:5000'],
            'location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['event_time'] = substr($data['event_time'], 0, 5);
        $package = Package::findOrFail($data['package_id']);

        // Check + insert in one transaction so two customers can't both take the last slot.
        $booking = DB::transaction(function () use ($data, $request, $package) {
            $this->availability->assertAvailable($data['event_date'], $data['event_time'], $data['location']);

            // Never trust the browser for money or ownership.
            return Booking::create($data + [
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'total_amount' => Booking::calculateTotal($package->price, $data['guests']),
            ]);
        });

        return redirect()
            ->route('customer.bookings.show', $booking->id)
            ->with('success', 'Booking request sent! We will confirm it shortly.');
    }

    public function show($id)
    {
        if ($this->isAdminArea()) {
            return parent::show($id);
        }

        $booking = Booking::with(['package', 'payments'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return view('customer.booking-show', compact('booking'));
    }

    /** Customers may cancel their own upcoming pending/confirmed bookings. */
    public function cancel($id)
    {
        $booking = Booking::where('user_id', auth()->id())->findOrFail($id);

        if (! $booking->isCancellableByCustomer()) {
            return back()->withErrors(['cancel' => 'This booking can no longer be cancelled online. Please contact us.']);
        }

        $booking->update(['status' => 'cancelled']);

        return redirect()
            ->route('customer.bookings.show', $booking->id)
            ->with('success', 'Your booking was cancelled.');
    }

    /* ---------------- admin update (wrapped so the availability check is atomic) ---------------- */

    public function update(Request $request, $id)
    {
        return DB::transaction(fn () => parent::update($request, $id));
    }

    /* ---------------- admin form definition ---------------- */

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'user_id', 'label' => 'Customer', 'type' => 'select', 'required' => true,
                'options' => User::where('role', 'user')->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'package_id', 'label' => 'Package', 'type' => 'select',
                'options' => Package::orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'event_name', 'label' => 'Event name', 'type' => 'text', 'required' => true],
            ['name' => 'event_date', 'label' => 'Event date', 'type' => 'date', 'required' => true],
            ['name' => 'event_time', 'label' => 'Start time', 'type' => 'time'],
            ['name' => 'guests', 'label' => 'Guests', 'type' => 'number', 'required' => true],
            ['name' => 'location', 'label' => 'Venue / location', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => self::STATUSES],
            ['name' => 'total_amount', 'label' => 'Total amount (₱) - blank = package price × guests', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'user')],
            'package_id' => ['nullable', 'exists:packages,id'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'guests' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item = null): array
    {
        if (! empty($data['event_time'])) {
            $data['event_time'] = substr($data['event_time'], 0, 5);
        }

        // Only live bookings take up capacity, so only they need the check.
        if (in_array($data['status'], Booking::ACTIVE_STATUSES, true)) {
            $this->availability->assertAvailable(
                $data['event_date'],
                $data['event_time'] ?? null,
                $data['location'] ?? null,
                $item?->id,
            );
        }

        $data['total_amount'] = $this->resolveTotal($data, $item);

        return $data;
    }

    /**
     * Blank total = recalculate. On edit the form pre-fills the old total, so if the
     * package or guest count changed but the total was left untouched, recalculate too.
     */
    private function resolveTotal(array $data, ?Booking $item): string
    {
        $submitted = $data['total_amount'] ?? null;
        $package = ! empty($data['package_id']) ? Package::find($data['package_id']) : null;

        $touchedPricing = $item && (
            (int) $item->package_id !== (int) ($data['package_id'] ?? 0)
            || (int) $item->guests !== (int) $data['guests']
        );
        $totalUntouched = $item && $submitted !== null && (float) $submitted === (float) $item->total_amount;

        if ($package && ($submitted === null || ($touchedPricing && $totalUntouched))) {
            return Booking::calculateTotal($package->price, (int) $data['guests']);
        }

        return (string) ($submitted ?? 0);
    }
}
