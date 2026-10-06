<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $table = 'receipts';

    protected $fillable = [
        'payment_id',
        'client_id',
        'booking_id',
        'receipt_number',
        'amount',
        'issued_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            Payment::class,
            'payment_id'
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
