<?php
// app/Models/Payment.php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'booking_id',
        'amount',
        'payment_method',
        'payment_date',
        'status',
        'reference_number',
        'proof_path',
        'submitted_by_customer',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'submitted_by_customer' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** e.g. OR-000042, printed on receipts. */
    public function getReceiptNumberAttribute(): string
    {
        return config('catering.receipt_prefix') . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
