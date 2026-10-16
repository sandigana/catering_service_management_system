<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'category',
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

    public function packages()
    {
        return $this->belongsToMany(
            Package::class,
            'package_menu_item'
        );
    }
}