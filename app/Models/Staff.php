<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'staff';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'position',
        'status',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_staff');
    }
}
