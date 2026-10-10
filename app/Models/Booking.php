<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'client_id',
        'booking_date',
        'start_time',
        'end_time',
        'subtotal',
        'deposit_amount',
        'paid_amount',
        'status',
        'payment_status',
        'notes',
        'cancelled_at',
        'cancellation_reason',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'subtotal' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Financial Attributes
    |--------------------------------------------------------------------------
    */

    public function getRemainingAmountAttribute(): float
    {
        return max(
            0,
            round(
                (float) $this->total_amount
                - (float) $this->paid_amount,
                2
            )
        );
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining_amount <= 0;
    }

    public function getOverpaidAmountAttribute(): float
    {
        return max(
            0,
            round(
                (float) $this->paid_amount
                - (float) $this->total_amount,
                2
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function bookingHolds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function referralRewardUsage(): HasOne
    {
        return $this->hasOne(
            ReferralRewardUsage::class,
            'booking_id'
        );
    }
    public function discountUsage(): HasOne
    {
        return $this->hasOne(
            DiscountUsage::class,
            'booking_id'
        );
    }
}
