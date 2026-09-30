<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHold extends Model
{
    protected $table = 'booking_holds';

    protected $fillable = [
        'booking_id',
        'staff_id',
        'hold_date',
        'start_time',
        'end_time',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'hold_date' => 'date',
        'expires_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expires_at
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || (
                $this->status === 'active'
                && $this->expires_at
                && $this->expires_at->isPast()
            );
    }
}
