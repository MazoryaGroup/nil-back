<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $table = 'expenses';

    protected $fillable = [
        'expense_category_id',
        'accounting_transaction_id',
        'amount',
        'payment_method',
        'expense_date',
        'reference_number',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ExpenseCategory::class,
            'expense_category_id'
        );
    }

    public function accountingTransaction(): BelongsTo
    {
        return $this->belongsTo(
            AccountingTransaction::class,
            'accounting_transaction_id'
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
