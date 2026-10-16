<?php
// app/Models/ActivityLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id',
        'description', 'changes', 'ip_address',
    ];

    protected $casts = ['changes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Write a free-form entry (login, logout, stock adjustments...). */
    public static function record(string $action, string $description, ?Model $subject = null, array $changes = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
