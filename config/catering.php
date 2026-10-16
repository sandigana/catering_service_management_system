<?php
// config/catering.php

return [
    // Maximum number of pending/confirmed events the kitchen can take on one date.
    'max_events_per_day' => (int) env('CATERING_MAX_EVENTS_PER_DAY', 3),

    // Two bookings at the same venue on the same date clash when their start
    // times are closer than this many hours.
    'venue_slot_hours' => (int) env('CATERING_VENUE_SLOT_HOURS', 4),

    // A stock item is "low" when its quantity is at or below its own threshold.
    'receipt_prefix' => env('CATERING_RECEIPT_PREFIX', 'OR-'),

    // Customers may request a new date only this many days (or more) before the event.
    'reschedule_min_days' => (int) env('CATERING_RESCHEDULE_MIN_DAYS', 2),

    // Reminder goes out this many days before a confirmed event.
    'reminder_days_before' => (int) env('CATERING_REMINDER_DAYS', 1),

    // Largest payment-proof upload, in kilobytes.
    'proof_max_kb' => (int) env('CATERING_PROOF_MAX_KB', 4096),
];
