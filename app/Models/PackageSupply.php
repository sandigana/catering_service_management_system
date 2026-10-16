<?php
// app/Models/PackageSupply.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageSupply extends Model
{
    protected $fillable = ['package_id', 'inventory_item_id', 'quantity_per_guest'];

    protected $casts = ['quantity_per_guest' => 'decimal:3'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
