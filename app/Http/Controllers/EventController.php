<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class EventController extends CrudController
{
    private const TYPES = ['Wedding', 'Birthday', 'Corporate', 'Debut', 'Anniversary', 'Other'];
    private const STATUSES = ['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected function model(): string { return Event::class; }
    protected function routeBase(): string { return 'admin.events'; }
    protected function label(): string { return 'Event'; }
    protected function relationKeys(): array { return ['staff_ids']; }

    public function index(Request $request)
    {
        $events = Event::with('booking')
            ->when($request->search, fn ($q, $s) => $q->where('event_name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('event_date')
            ->paginate(15)
            ->withQueryString();

        return view('administrator.events', compact('events'));
    }

    protected function fields(Model $item): array
    {
        $bookings = Booking::orderByDesc('id')->limit(300)->get()
            ->mapWithKeys(fn ($b) => [$b->id => "#{$b->id} - {$b->event_name}"])
            ->all();

        return [
            ['name' => 'booking_id', 'label' => 'Booking', 'type' => 'select', 'options' => $bookings],
            ['name' => 'event_name', 'label' => 'Event name', 'type' => 'text', 'required' => true],
            ['name' => 'event_type', 'label' => 'Type', 'type' => 'select',
                'options' => array_combine(self::TYPES, self::TYPES)],
            ['name' => 'event_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'event_time', 'label' => 'Time', 'type' => 'time'],
            ['name' => 'location', 'label' => 'Location', 'type' => 'text'],
            ['name' => 'guests', 'label' => 'Guests', 'type' => 'number', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true,
                'options' => self::STATUSES],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ['name' => 'staff_ids', 'label' => 'Assigned staff', 'type' => 'checkboxes',
                'options' => Staff::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
                'selected' => $item->exists ? $item->staff()->pluck('staff.id')->all() : []],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'location' => ['nullable', 'string', 'max:255'],
            'guests' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
            'notes' => ['nullable', 'string'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['exists:staff,id'],
        ];
    }

    protected function afterSave(Model $item, Request $request): void
    {
        $item->staff()->sync($request->input('staff_ids', []));
    }
}
