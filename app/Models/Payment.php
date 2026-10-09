<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'booking_id',
        'refunded_payment_id',
        'client_id',
        'amount',
        'type',
        'payment_method',
        'status',
        'gateway',
        'transaction_id',
        'reference_number',
        'authority',
        'offline_id',
        'paid_at',
        'initiation_token',
        'initiation_expires_at',
        'pos_terminal_id',
        'short_link_token',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'initiation_expires_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function refundedPayment(): BelongsTo
    {
        return $this->belongsTo(
            Payment::class,
            'refunded_payment_id'
        );
    }

    public function refunds()
    {
        return $this->hasMany(
            Payment::class,
            'refunded_payment_id'
        );
    }

    public function accountingTransactions()
    {
        return $this->hasMany(
            AccountingTransaction::class,
            'payment_id'
        );
    }
    public function posTerminal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            \App\Models\PosTerminal::class,
            'pos_terminal_id'
        );
    }

    public function posPaymentRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            \App\Models\PosPaymentRequest::class,
            'payment_id'
        );
    }
}
