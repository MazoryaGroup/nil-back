<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingService extends Model
{
    protected $table = 'booking_services';

    protected $fillable = [
        'booking_id',
        'service_id',
        'staff_id',
        'start_time',
        'end_time',
        'duration',
        'price',
        'deposit_amount',
        'final_price',
        'price_adjustment_reason',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'final_price' => 'decimal:2',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->final_price ?? $this->price);
    }
}
