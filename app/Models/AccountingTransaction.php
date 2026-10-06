<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingTransaction extends Model
{
    protected $table = 'accounting_transactions';

    protected $fillable = [
        'client_id',
        'booking_id',
        'payment_id',
        'type',
        'category',
        'payment_method',
        'amount',
        'reference_number',
        'description',
        'transaction_date',
        'status',
        'offline_id',
        'synced_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'synced_at' => 'datetime',
    ];

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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            Payment::class,
            'payment_id'
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
