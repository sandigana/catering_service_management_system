<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /** Per-guest stock recipe, e.g. 0.25 kg rice per guest. */
    public function supplies()
    {
        return $this->hasMany(PackageSupply::class)->with('item');
    }

    public function menuItems()
    {
        return $this->belongsToMany(
            MenuItem::class,
            'package_menu_item'
        );
    }
}