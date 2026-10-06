<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountUsage extends Model
{
    protected $table = 'discount_usages';

    protected $fillable = [
        'discount_code_id',
        'client_id',
        'booking_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(
            DiscountCode::class,
            'discount_code_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class,
            'client_id'
        );
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(
            Booking::class,
            'booking_id'
        );
    }
}
