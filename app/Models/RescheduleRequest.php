<?php
// app/Models/RescheduleRequest.php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class RescheduleRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'booking_id', 'requested_date', 'requested_time', 'reason',
        'status', 'decided_by', 'decided_at', 'admin_note',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'decided_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
