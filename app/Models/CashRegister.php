<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $table = 'cash_registers';

    protected $fillable = [
        'register_date',
        'opening_balance',
        'total_income',
        'total_expense',
        'total_refund',
        'closing_balance',
        'actual_closing_balance',
        'cash_difference',
        'status',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'register_date' => 'date',
        'opening_balance' => 'decimal:2',
        'total_income' => 'decimal:2',
        'total_expense' => 'decimal:2',
        'total_refund' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'actual_closing_balance' => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'opened_by'
        );
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            CashRegisterTransaction::class,
            'cash_register_id'
        );
    }
}
