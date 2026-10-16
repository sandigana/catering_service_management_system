<?php
// app/Models/Booking.php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    protected $fillable = [
        'user_id',
        'package_id',
        'event_name',
        'event_date',
        'event_time',
        'status',
        'guests',
        'location',
        'notes',
        'total_amount',
        'reminder_sent_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'total_amount' => 'decimal:2',
        'reminder_sent_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function event()
    {
        return $this->hasOne(Event::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function rescheduleRequests()
    {
        return $this->hasMany(RescheduleRequest::class);
    }

    public function pendingReschedule()
    {
        return $this->hasOne(RescheduleRequest::class)->where('status', 'pending')->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    /** price x guests, computed in centavos so floats never drift. */
    public static function calculateTotal(float|string $unitPrice, int $guests): string
    {
        $centavos = (int) round(((float) $unitPrice) * 100) * $guests;

        return number_format($centavos / 100, 2, '.', '');
    }

    /** Adds a paid_sum column in SQL so lists never need to load every payment. */
    public function scopeWithPaid($query)
    {
        return $query->withSum(['payments as paid_sum' => fn ($p) => $p->where('status', 'paid')], 'amount');
    }

    /** Sum of payments that were actually received. */
    public function getAmountPaidAttribute(): string
    {
        if (array_key_exists('paid_sum', $this->attributes)) {
            $paid = (float) $this->attributes['paid_sum'];
        } elseif ($this->relationLoaded('payments')) {
            $paid = $this->payments->where('status', 'paid')->sum('amount');
        } else {
            $paid = $this->payments()->where('status', 'paid')->sum('amount');
        }

        return number_format((float) $paid, 2, '.', '');
    }

    public function getBalanceAttribute(): string
    {
        return number_format(max(0, (float) $this->total_amount - (float) $this->amount_paid), 2, '.', '');
    }

    /** unpaid | partial | paid */
    public function getPaymentStatusAttribute(): string
    {
        $paid = (float) $this->amount_paid;
        $total = (float) $this->total_amount;

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid + 0.004 >= $total ? 'paid' : 'partial';
    }

    /** Live booking, far enough ahead, with no request already waiting. */
    public function isReschedulableByCustomer(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            && $this->event_date->gte(today()->addDays((int) config('catering.reschedule_min_days')))
            && ! $this->rescheduleRequests()->where('status', 'pending')->exists();
    }

    public function isCancellableByCustomer(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            && $this->event_date->isFuture();
    }

    /** Plain-text facts used by the e-mails. */
    public function summaryLines(): array
    {
        $when = $this->event_date->format('F j, Y');

        if ($this->event_time) {
            $when .= ' at ' . \Carbon\Carbon::parse($this->event_time)->format('g:i A');
        }

        return array_filter([
            "Event: {$this->event_name}",
            "When: {$when}",
            $this->location ? "Venue: {$this->location}" : null,
            "Guests: {$this->guests}",
            'Total: ₱' . number_format((float) $this->total_amount, 2),
            'Balance: ₱' . number_format((float) $this->balance, 2),
        ]);
    }
}
