<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    protected $table = 'pos_terminals';

    protected $fillable = [
        'name',
        'provider',
        'bank_name',
        'terminal_id',
        'merchant_id',
        'connection_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class,
            'pos_terminal_id'
        );
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(
            PosPaymentRequest::class,
            'pos_terminal_id'
        );
    }
}
