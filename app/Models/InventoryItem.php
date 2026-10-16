<?php
// app/Models/InventoryItem.php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'unit', 'quantity', 'low_stock_threshold', 'status'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_inventory')->withPivot('quantity')->withTimestamps();
    }

    public function isLow(): bool
    {
        return (float) $this->quantity <= (float) $this->low_stock_threshold;
    }

    public function scopeLow($query)
    {
        return $query->whereColumn('quantity', '<=', 'low_stock_threshold');
    }
}
