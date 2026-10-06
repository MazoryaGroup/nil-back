<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterTransaction extends Model
{
    protected $table = 'cash_register_transactions';

    protected $fillable = [
        'cash_register_id',
        'accounting_transaction_id',
        'payment_id',
        'expense_id',
        'type',
        'amount',
        'offline_id',
        'reference_number',
        'description',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(
            CashRegister::class,
            'cash_register_id'
        );
    }

    public function accountingTransaction(): BelongsTo
    {
        return $this->belongsTo(
            AccountingTransaction::class,
            'accounting_transaction_id'
        );
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            Payment::class,
            'payment_id'
        );
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(
            Expense::class,
            'expense_id'
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
