<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'booking_id',
        'event_name',
        'event_date',
        'event_time',
        'location',
        'event_type',
        'guests',
        'status',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'event_staff');
    }

    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'event_inventory')->withPivot('id', 'quantity')->withTimestamps();
    }
}
