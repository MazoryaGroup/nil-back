<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashRegisterService
{
    public function getOrCreateTodayRegister(
        ?int $createdBy = null
    ): CashRegister {

        return DB::transaction(function () use ($createdBy) {

            $registerDate = now()->toDateString();

            $register = CashRegister::query()
                ->where('register_date', $registerDate)
                ->lockForUpdate()
                ->first();

            if ($register) {
                return $register;
            }

            return CashRegister::create([
                'register_date' => $registerDate,
                'opening_balance' => 0,
                'total_income' => 0,
                'total_expense' => 0,
                'total_refund' => 0,
                'closing_balance' => 0,
                'status' => 'open',
                'opened_at' => now(),
                'closed_at' => null,
                'opened_by' => $createdBy,
                'closed_by' => null,
                'notes' => null,
            ]);
        });
    }

    public function getRegisterBalance(
        CashRegister $register
    ): float {

        return max(
            0,
            (float) $register->opening_balance
            + (float) $register->total_income
            - (float) $register->total_expense
            - (float) $register->total_refund
        );
    }
    public function addIncome(
        \App\Models\AccountingTransaction $accountingTransaction,
        ?int $createdBy = null
    ): \App\Models\CashRegisterTransaction {

        if ($accountingTransaction->payment_method !== 'cash') {
            throw new RuntimeException(
                'Only cash accounting transactions can enter the cash register.'
            );
        }

        if ($accountingTransaction->type !== 'income') {
            throw new RuntimeException(
                'Only income transactions can be added as cash income.'
            );
        }

        if ($accountingTransaction->status !== 'completed') {
            throw new RuntimeException(
                'Only completed accounting transactions can enter the cash register.'
            );
        }

        return DB::transaction(function () use (
            $accountingTransaction,
            $createdBy
        ) {

            $register = CashRegister::query()
                ->where(
                    'register_date',
                    $accountingTransaction->transaction_date->toDateString()
                )
                ->lockForUpdate()
                ->first();

            if (!$register) {
                $register = CashRegister::create([
                    'register_date' => $accountingTransaction
                        ->transaction_date
                        ->toDateString(),

                    'opening_balance' => 0,
                    'total_income' => 0,
                    'total_expense' => 0,
                    'total_refund' => 0,
                    'closing_balance' => 0,

                    'status' => 'open',

                    'opened_at' => now(),
                    'closed_at' => null,

                    'opened_by' => $createdBy,
                    'closed_by' => null,

                    'notes' => null,
                ]);
            }

            if ($register->status === 'closed') {
                throw new RuntimeException(
                    'This cash register is already closed.'
                );
            }

            $existingTransaction = CashRegisterTransaction::query()
                ->where(
                    'accounting_transaction_id',
                    $accountingTransaction->id
                )
                ->first();

            if ($existingTransaction) {
                return $existingTransaction;
            }

            $cashTransaction = CashRegisterTransaction::create([
                'cash_register_id' => $register->id,

                'accounting_transaction_id' =>
                    $accountingTransaction->id,

                'payment_id' =>
                    $accountingTransaction->payment_id,

                'expense_id' => null,

                'type' => 'income',

                'amount' => $accountingTransaction->amount,

                'reference_number' =>
                    $accountingTransaction->reference_number,

                'description' =>
                    $accountingTransaction->description,

                'transaction_date' =>
                    $accountingTransaction->transaction_date,

                'created_by' => $createdBy,
            ]);

            $register->increment(
                'total_income',
                $accountingTransaction->amount
            );

            $register->refresh();

            $register->update([
                'closing_balance' =>
                    $this->getRegisterBalance($register),
            ]);

            return $cashTransaction->fresh();
        });
    }
    public function addExpense(
        \App\Models\AccountingTransaction $accountingTransaction,
        ?int $expenseId = null,
        ?int $createdBy = null
    ): \App\Models\CashRegisterTransaction {

        if ($accountingTransaction->payment_method !== 'cash') {
            throw new RuntimeException(
                'Only cash accounting transactions can enter the cash register.'
            );
        }

        if ($accountingTransaction->type !== 'expense') {
            throw new RuntimeException(
                'Only expense transactions can be added as cash expense.'
            );
        }

        if ($accountingTransaction->status !== 'completed') {
            throw new RuntimeException(
                'Only completed accounting transactions can enter the cash register.'
            );
        }

        return DB::transaction(function () use (
            $accountingTransaction,
            $expenseId,
            $createdBy
        ) {

            $register = CashRegister::query()
                ->where(
                    'register_date',
                    $accountingTransaction->transaction_date->toDateString()
                )
                ->lockForUpdate()
                ->first();

            if (!$register) {
                $register = CashRegister::create([
                    'register_date' => $accountingTransaction
                        ->transaction_date
                        ->toDateString(),

                    'opening_balance' => 0,
                    'total_income' => 0,
                    'total_expense' => 0,
                    'total_refund' => 0,
                    'closing_balance' => 0,

                    'status' => 'open',

                    'opened_at' => now(),
                    'closed_at' => null,

                    'opened_by' => $createdBy,
                    'closed_by' => null,

                    'notes' => null,
                ]);
            }

            if ($register->status === 'closed') {
                throw new RuntimeException(
                    'This cash register is already closed.'
                );
            }

            $existingTransaction = CashRegisterTransaction::query()
                ->where(
                    'accounting_transaction_id',
                    $accountingTransaction->id
                )
                ->first();

            if ($existingTransaction) {
                return $existingTransaction;
            }

            $cashTransaction = CashRegisterTransaction::create([
                'cash_register_id' =>
                    $register->id,

                'accounting_transaction_id' =>
                    $accountingTransaction->id,

                'payment_id' => null,

                'expense_id' => $expenseId,

                'type' => 'expense',

                'amount' =>
                    $accountingTransaction->amount,

                'reference_number' =>
                    $accountingTransaction->reference_number,

                'description' =>
                    $accountingTransaction->description,

                'transaction_date' =>
                    $accountingTransaction->transaction_date,

                'created_by' => $createdBy,
            ]);

            $register->increment(
                'total_expense',
                $accountingTransaction->amount
            );

            $register->refresh();

            $register->update([
                'closing_balance' =>
                    $this->getRegisterBalance($register),
            ]);

            return $cashTransaction->fresh();
        });
    }
    public function addRefund(
        \App\Models\AccountingTransaction $accountingTransaction,
        ?int $createdBy = null
    ): \App\Models\CashRegisterTransaction {

        if ($accountingTransaction->payment_method !== 'cash') {
            throw new RuntimeException(
                'Only cash refund transactions can enter the cash register.'
            );
        }

        if ($accountingTransaction->type !== 'refund') {
            throw new RuntimeException(
                'Only refund transactions can be added as cash refund.'
            );
        }

        if ($accountingTransaction->status !== 'completed') {
            throw new RuntimeException(
                'Only completed accounting transactions can enter the cash register.'
            );
        }

        return DB::transaction(function () use (
            $accountingTransaction,
            $createdBy
        ) {

            $register = CashRegister::query()
                ->where(
                    'register_date',
                    $accountingTransaction->transaction_date->toDateString()
                )
                ->lockForUpdate()
                ->first();

            if (!$register) {
                $register = CashRegister::create([
                    'register_date' => $accountingTransaction
                        ->transaction_date
                        ->toDateString(),

                    'opening_balance' => 0,
                    'total_income' => 0,
                    'total_expense' => 0,
                    'total_refund' => 0,
                    'closing_balance' => 0,

                    'status' => 'open',

                    'opened_at' => now(),
                    'closed_at' => null,

                    'opened_by' => $createdBy,
                    'closed_by' => null,

                    'notes' => null,
                ]);
            }

            if ($register->status === 'closed') {
                throw new RuntimeException(
                    'This cash register is already closed.'
                );
            }

            $existingTransaction = CashRegisterTransaction::query()
                ->where(
                    'accounting_transaction_id',
                    $accountingTransaction->id
                )
                ->first();

            if ($existingTransaction) {
                return $existingTransaction;
            }

            $cashTransaction = CashRegisterTransaction::create([
                'cash_register_id' =>
                    $register->id,

                'accounting_transaction_id' =>
                    $accountingTransaction->id,

                'payment_id' =>
                    $accountingTransaction->payment_id,

                'expense_id' => null,

                'type' => 'refund',

                'amount' =>
                    $accountingTransaction->amount,

                'reference_number' =>
                    $accountingTransaction->reference_number,

                'description' =>
                    $accountingTransaction->description,

                'transaction_date' =>
                    $accountingTransaction->transaction_date,

                'created_by' => $createdBy,
            ]);

            $register->increment(
                'total_refund',
                $accountingTransaction->amount
            );

            $register->refresh();

            $register->update([
                'closing_balance' =>
                    $this->getRegisterBalance($register),
            ]);

            return $cashTransaction->fresh();
        });
    }
    public function addCashIn(
        float $amount,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): \App\Models\CashRegisterTransaction {

        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if ($amount <= 0) {
            throw new RuntimeException(
                'Cash in amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Reference Number
        |--------------------------------------------------------------------------
        */

        if ($referenceNumber !== null) {
            $referenceNumber = trim($referenceNumber);

            if ($referenceNumber === '') {
                $referenceNumber = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Offline ID
        |--------------------------------------------------------------------------
        */

        if ($offlineId !== null) {
            $offlineId = trim($offlineId);

            if ($offlineId === '') {
                $offlineId = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check Existing Offline Transaction
        |--------------------------------------------------------------------------
        */

        if ($offlineId !== null) {

            $existingTransaction =
                CashRegisterTransaction::query()
                    ->where('offline_id', $offlineId)
                    ->first();

            if ($existingTransaction) {

                if ($existingTransaction->type !== 'cash_in') {
                    throw new RuntimeException(
                        'Offline ID already belongs to another cash transaction.'
                    );
                }

                return $existingTransaction;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Cash In
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $amount,
            $referenceNumber,
            $description,
            $createdBy,
            $offlineId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Recheck Offline ID
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {

                $existingTransaction =
                    CashRegisterTransaction::query()
                        ->where('offline_id', $offlineId)
                        ->lockForUpdate()
                        ->first();

                if ($existingTransaction) {

                    if ($existingTransaction->type !== 'cash_in') {
                        throw new RuntimeException(
                            'Offline ID already belongs to another cash transaction.'
                        );
                    }

                    return $existingTransaction;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Get Today Register
            |--------------------------------------------------------------------------
            */

            $register = $this->getOrCreateTodayRegister(
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Lock Register
            |--------------------------------------------------------------------------
            */

            $register = CashRegister::query()
                ->where('id', $register->id)
                ->lockForUpdate()
                ->first();

            if (!$register) {
                throw new RuntimeException(
                    'Cash register not found.'
                );
            }

            if ($register->status === 'closed') {
                throw new RuntimeException(
                    'This cash register is already closed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'closing_balance' =>
                    (float) $register->closing_balance,

                'total_income' =>
                    (float) $register->total_income,
            ];

            /*
            |--------------------------------------------------------------------------
            | Create Cash Transaction
            |--------------------------------------------------------------------------
            */

            $cashTransaction =
                CashRegisterTransaction::create([

                    'cash_register_id' =>
                        $register->id,

                    'accounting_transaction_id' =>
                        null,

                    'payment_id' =>
                        null,

                    'expense_id' =>
                        null,

                    'type' =>
                        'cash_in',

                    'amount' =>
                        $amount,

                    'reference_number' =>
                        $referenceNumber,

                    'offline_id' =>
                        $offlineId,

                    'description' =>
                        $description
                        ?? 'Manual cash in',

                    'transaction_date' =>
                        now(),

                    'created_by' =>
                        $createdBy,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Update Register
            |--------------------------------------------------------------------------
            */

            $register->increment(
                'total_income',
                $amount
            );

            $register->refresh();

            $register->update([
                'closing_balance' =>
                    $this->getRegisterBalance(
                        $register
                    ),
            ]);

            $register->refresh();
            $cashTransaction->refresh();

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(

                action: 'cash_in',

                entityType:
                'cash_register_transaction',

                entityId:
                $cashTransaction->id,

                bookingId:
                null,

                clientId:
                null,

                description:
                $description
                ?? 'Manual cash added to cash register',

                oldValues:
                $oldValues,

                newValues: [

                    'cash_register_id' =>
                        $register->id,

                    'cash_register_transaction_id' =>
                        $cashTransaction->id,

                    'amount' =>
                        (float) $cashTransaction->amount,

                    'reference_number' =>
                        $cashTransaction->reference_number,

                    'offline_id' =>
                        $cashTransaction->offline_id,

                    'total_income' =>
                        (float) $register->total_income,

                    'closing_balance' =>
                        (float) $register->closing_balance,

                    'transaction_date' =>
                        $cashTransaction
                            ->transaction_date
                            ?->toDateTimeString(),
                ],

                userId:
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return Transaction
            |--------------------------------------------------------------------------
            */

            return $cashTransaction->fresh();
        });
    }
    public function addCashOut(
        float $amount,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): \App\Models\CashRegisterTransaction {

        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if ($amount <= 0) {
            throw new RuntimeException(
                'Cash out amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Reference Number
        |--------------------------------------------------------------------------
        */

        if ($referenceNumber !== null) {
            $referenceNumber = trim($referenceNumber);

            if ($referenceNumber === '') {
                $referenceNumber = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Offline ID
        |--------------------------------------------------------------------------
        */

        if ($offlineId !== null) {
            $offlineId = trim($offlineId);

            if ($offlineId === '') {
                $offlineId = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check Existing Offline Transaction
        |--------------------------------------------------------------------------
        */

        if ($offlineId !== null) {

            $existingTransaction =
                CashRegisterTransaction::query()
                    ->where('offline_id', $offlineId)
                    ->first();

            if ($existingTransaction) {

                if ($existingTransaction->type !== 'cash_out') {
                    throw new RuntimeException(
                        'Offline ID already belongs to another cash transaction.'
                    );
                }

                return $existingTransaction;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Cash Out
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $amount,
            $referenceNumber,
            $description,
            $createdBy,
            $offlineId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Recheck Offline ID Inside Transaction
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {

                $existingTransaction =
                    CashRegisterTransaction::query()
                        ->where('offline_id', $offlineId)
                        ->lockForUpdate()
                        ->first();

                if ($existingTransaction) {

                    if ($existingTransaction->type !== 'cash_out') {
                        throw new RuntimeException(
                            'Offline ID already belongs to another cash transaction.'
                        );
                    }

                    return $existingTransaction;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Get Today Register
            |--------------------------------------------------------------------------
            */

            $register = $this->getOrCreateTodayRegister(
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Lock Register
            |--------------------------------------------------------------------------
            */

            $register = CashRegister::query()
                ->where('id', $register->id)
                ->lockForUpdate()
                ->first();

            if (!$register) {
                throw new RuntimeException(
                    'Cash register not found.'
                );
            }

            if ($register->status === 'closed') {
                throw new RuntimeException(
                    'This cash register is already closed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Check Balance
            |--------------------------------------------------------------------------
            */

            $currentBalance =
                $this->getRegisterBalance(
                    $register
                );

            if ($amount > $currentBalance) {
                throw new RuntimeException(
                    'Insufficient cash register balance.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'closing_balance' =>
                    (float) $register->closing_balance,

                'total_expense' =>
                    (float) $register->total_expense,
            ];

            /*
            |--------------------------------------------------------------------------
            | Create Cash Transaction
            |--------------------------------------------------------------------------
            */

            $cashTransaction =
                CashRegisterTransaction::create([

                    'cash_register_id' =>
                        $register->id,

                    'accounting_transaction_id' =>
                        null,

                    'payment_id' =>
                        null,

                    'expense_id' =>
                        null,

                    'type' =>
                        'cash_out',

                    'amount' =>
                        $amount,

                    'reference_number' =>
                        $referenceNumber,

                    'offline_id' =>
                        $offlineId,

                    'description' =>
                        $description
                        ?? 'Manual cash out',

                    'transaction_date' =>
                        now(),

                    'created_by' =>
                        $createdBy,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Update Register
            |--------------------------------------------------------------------------
            */

            $register->increment(
                'total_expense',
                $amount
            );

            $register->refresh();

            $register->update([
                'closing_balance' =>
                    $this->getRegisterBalance(
                        $register
                    ),
            ]);

            $register->refresh();
            $cashTransaction->refresh();

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(

                action: 'cash_out',

                entityType:
                'cash_register_transaction',

                entityId:
                $cashTransaction->id,

                bookingId:
                null,

                clientId:
                null,

                description:
                $description
                ?? 'Manual cash removed from cash register',

                oldValues:
                $oldValues,

                newValues: [

                    'cash_register_id' =>
                        $register->id,

                    'cash_register_transaction_id' =>
                        $cashTransaction->id,

                    'amount' =>
                        (float) $cashTransaction->amount,

                    'reference_number' =>
                        $cashTransaction->reference_number,

                    'offline_id' =>
                        $cashTransaction->offline_id,

                    'total_expense' =>
                        (float) $register->total_expense,

                    'closing_balance' =>
                        (float) $register->closing_balance,

                    'transaction_date' =>
                        $cashTransaction
                            ->transaction_date
                            ?->toDateTimeString(),
                ],

                userId:
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return Transaction
            |--------------------------------------------------------------------------
            */

            return $cashTransaction->fresh();
        });
    }
    public function closeRegister(
        \App\Models\CashRegister $register,
        float $actualClosingBalance,
        ?int $closedBy = null,
        ?string $notes = null
    ): \App\Models\CashRegister {

        if ($actualClosingBalance < 0) {
            throw new RuntimeException(
                'Actual closing balance cannot be negative.'
            );
        }

        return DB::transaction(function () use (
            $register,
            $actualClosingBalance,
            $closedBy,
            $notes
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Register
            |--------------------------------------------------------------------------
            */

            $register = CashRegister::query()
                ->where('id', $register->id)
                ->lockForUpdate()
                ->first();

            if (!$register) {
                throw new RuntimeException(
                    'Cash register not found.'
                );
            }

            if ($register->status === 'closed') {
                throw new RuntimeException(
                    'This cash register is already closed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'status' => $register->status,

                'opening_balance' =>
                    (float) $register->opening_balance,

                'total_income' =>
                    (float) $register->total_income,

                'total_expense' =>
                    (float) $register->total_expense,

                'total_refund' =>
                    (float) $register->total_refund,

                'closing_balance' =>
                    (float) $register->closing_balance,

                'actual_closing_balance' =>
                    $register->actual_closing_balance !== null
                        ? (float) $register->actual_closing_balance
                        : null,

                'cash_difference' =>
                    $register->cash_difference !== null
                        ? (float) $register->cash_difference
                        : null,
            ];

            /*
            |--------------------------------------------------------------------------
            | Calculate System Balance
            |--------------------------------------------------------------------------
            */

            $closingBalance = $this->getRegisterBalance(
                $register
            );

            /*
            |--------------------------------------------------------------------------
            | Calculate Cash Difference
            |--------------------------------------------------------------------------
            */

            $cashDifference =
                $actualClosingBalance - $closingBalance;

            /*
            |--------------------------------------------------------------------------
            | Close Register
            |--------------------------------------------------------------------------
            */

            $register->update([
                'closing_balance' =>
                    $closingBalance,

                'actual_closing_balance' =>
                    $actualClosingBalance,

                'cash_difference' =>
                    $cashDifference,

                'status' =>
                    'closed',

                'closed_at' =>
                    now(),

                'closed_by' =>
                    $closedBy,

                'notes' =>
                    $notes ?? $register->notes,
            ]);

            $register->refresh();

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(
                action: 'cash_register_closed',

                entityType: 'cash_register',

                entityId: $register->id,

                bookingId: null,

                clientId: null,

                description: $notes
                ?? 'Cash register closed',

                oldValues: $oldValues,

                newValues: [
                    'status' =>
                        $register->status,

                    'opening_balance' =>
                        (float) $register->opening_balance,

                    'total_income' =>
                        (float) $register->total_income,

                    'total_expense' =>
                        (float) $register->total_expense,

                    'total_refund' =>
                        (float) $register->total_refund,

                    'closing_balance' =>
                        (float) $register->closing_balance,

                    'actual_closing_balance' =>
                        (float) $register->actual_closing_balance,

                    'cash_difference' =>
                        (float) $register->cash_difference,

                    'closed_at' =>
                        $register->closed_at?->toDateTimeString(),

                    'closed_by' =>
                        $register->closed_by,

                    'notes' =>
                        $register->notes,
                ],

                userId: $closedBy
            );

            return $register->fresh();
        });
    }
}
