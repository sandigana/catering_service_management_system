<?php
// app/Http/Controllers/EventInventoryController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reserve stock for an event (deducts inventory) and release it again. */
class EventInventoryController extends Controller
{
    public function index($eventId)
    {
        $event = Event::with(['inventoryItems', 'booking.package'])->findOrFail($eventId);

        return view('administrator.event-supplies', [
            'event' => $event,
            'items' => InventoryItem::where('status', 'active')->orderBy('name')->get(),
            'plan' => $this->plan($event),
        ]);
    }

    public function store(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ]);

        DB::transaction(function () use ($event, $data) {
            $this->reserve($event, (int) $data['inventory_item_id'], (float) $data['quantity'], 'quantity');
        });

        return back()->with('success', 'Supplies reserved for this event.');
    }

    /**
     * Reserves whatever the package recipe still needs (needed - already reserved), all or nothing.
     * Safe to press twice: the second time nothing is missing, so nothing more is taken.
     */
    public function fromPackage($eventId)
    {
        $event = Event::with(['inventoryItems', 'booking.package'])->findOrFail($eventId);

        DB::transaction(function () use ($event) {
            $missing = [];

            foreach ($this->plan($event) as $row) {
                if ($row['shortfall'] <= 0) {
                    continue;
                }

                try {
                    $this->reserve($event, $row['item']->id, $row['shortfall'], 'package');
                } catch (ValidationException) {
                    $missing[] = "{$row['item']->name} (need {$row['shortfall']} {$row['item']->unit}, have {$row['item']->quantity})";
                }
            }

            // Any shortage cancels the whole batch, so stock never ends up half-reserved.
            if ($missing) {
                throw ValidationException::withMessages(['package' => 'Not enough stock for: ' . implode('; ', $missing) . '. Nothing was reserved.']);
            }
        });

        return back()->with('success', 'Supplies reserved from the package recipe.');
    }

    /** Remove an allocation and put the stock back. */
    public function destroy($eventId, $itemId)
    {
        $event = Event::findOrFail($eventId);

        DB::transaction(function () use ($event, $itemId) {
            $item = InventoryItem::lockForUpdate()->findOrFail($itemId);
            $allocated = $event->inventoryItems()->where('inventory_items.id', $item->id)->first();

            abort_unless($allocated, 404);

            $item->increment('quantity', $allocated->pivot->quantity);
            $event->inventoryItems()->detach($item->id);

            ActivityLog::record('stock_released', "{$allocated->pivot->quantity} {$item->unit} of {$item->name} returned from event #{$event->id}", $item);
        });

        return back()->with('success', 'Supplies returned to stock.');
    }

    /** Per-item needs from the package recipe: needed, already reserved, still missing. */
    private function plan(Event $event): array
    {
        $package = $event->booking?->package;
        $guests = (int) ($event->guests ?: $event->booking?->guests);

        if (! $package || $guests < 1) {
            return [];
        }

        $reserved = $event->inventoryItems->mapWithKeys(fn ($i) => [$i->id => (float) $i->pivot->quantity]);

        return $package->supplies->map(function ($supply) use ($guests, $reserved) {
            $needed = ceil((float) $supply->quantity_per_guest * $guests * 100) / 100;
            $have = (float) ($reserved[$supply->inventory_item_id] ?? 0);

            return [
                'item' => $supply->item,
                'per_guest' => (float) $supply->quantity_per_guest,
                'needed' => $needed,
                'reserved' => $have,
                'shortfall' => round(max(0, $needed - $have), 2),
            ];
        })->all();
    }

    /** Lock the item, check stock, deduct and attach/increment the event allocation. */
    private function reserve(Event $event, int $itemId, float $quantity, string $errorKey): void
    {
        $item = InventoryItem::lockForUpdate()->findOrFail($itemId);

        // Compare in hundredths so float noise can't reject an exact amount.
        if (round($quantity * 100) > round((float) $item->quantity * 100)) {
            throw ValidationException::withMessages([
                $errorKey => "Only {$item->quantity} {$item->unit} of {$item->name} available.",
            ]);
        }

        $item->decrement('quantity', $quantity);

        $existing = $event->inventoryItems()->where('inventory_items.id', $item->id)->first();

        if ($existing) {
            $event->inventoryItems()->updateExistingPivot($item->id, [
                'quantity' => round((float) $existing->pivot->quantity + $quantity, 2),
            ]);
        } else {
            $event->inventoryItems()->attach($item->id, ['quantity' => $quantity]);
        }

        ActivityLog::record('stock_reserved', "{$quantity} {$item->unit} of {$item->name} reserved for event #{$event->id}", $item);
    }
}
