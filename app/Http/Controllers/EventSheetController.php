<?php
// app/Http/Controllers/EventSheetController.php

namespace App\Http\Controllers;

use App\Models\Event;

/** One printable page with everything the team needs on the day of an event. */
class EventSheetController extends Controller
{
    public function show($id)
    {
        $event = Event::with(['booking.user', 'booking.package.menuItems', 'staff', 'inventoryItems'])->findOrFail($id);

        return view('administrator.event-sheet', [
            'event' => $event,
            'booking' => $event->booking,
        ]);
    }
}
