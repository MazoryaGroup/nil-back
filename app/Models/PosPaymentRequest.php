<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPaymentRequest extends Model
{
    protected $table = 'pos_payment_requests';

    protected $fillable = [
        'payment_id',
        'pos_terminal_id',
        'request_token',
        'status',
        'amount',
        'provider_transaction_id',
        'reference_number',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            Payment::class,
            'payment_id'
        );
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(
            PosTerminal::class,
            'pos_terminal_id'
        );
    }
}
