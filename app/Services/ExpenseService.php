<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseService
{
    public function createExpense(
        int $expenseCategoryId,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Expense {

        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if ($amount <= 0) {
            throw new RuntimeException(
                'Expense amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Payment Method
        |--------------------------------------------------------------------------
        */

        $allowedPaymentMethods = [
            'cash',
            'pos',
            'bank_transfer',
            'other',
        ];

        if (!in_array(
            $paymentMethod,
            $allowedPaymentMethods,
            true
        )) {
            throw new RuntimeException(
                'Invalid expense payment method.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Reference Number
        |--------------------------------------------------------------------------
        */

        if ($referenceNumber !== null) {

            $referenceNumber = trim(
                $referenceNumber
            );

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

            $offlineId = trim(
                $offlineId
            );

            if ($offlineId === '') {
                $offlineId = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check Existing Offline Expense
        |--------------------------------------------------------------------------
        |
        | اگر PWA درخواست را دوباره ارسال کرد، Expense جدید ساخته نمی‌شود.
        |
        */

        if ($offlineId !== null) {

            $existingAccounting =
                AccountingTransaction::query()
                    ->where(
                        'offline_id',
                        $offlineId
                    )
                    ->where(
                        'type',
                        'expense'
                    )
                    ->first();

            if ($existingAccounting) {

                $existingExpense = Expense::query()
                    ->where(
                        'accounting_transaction_id',
                        $existingAccounting->id
                    )
                    ->first();

                if ($existingExpense) {
                    return $existingExpense;
                }

                throw new RuntimeException(
                    'Offline expense accounting transaction already exists.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Expense
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $expenseCategoryId,
            $amount,
            $paymentMethod,
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

                $existingAccounting =
                    AccountingTransaction::query()
                        ->where(
                            'offline_id',
                            $offlineId
                        )
                        ->where(
                            'type',
                            'expense'
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existingAccounting) {

                    $existingExpense = Expense::query()
                        ->where(
                            'accounting_transaction_id',
                            $existingAccounting->id
                        )
                        ->first();

                    if ($existingExpense) {
                        return $existingExpense;
                    }

                    throw new RuntimeException(
                        'Offline expense accounting transaction already exists.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Expense
            |--------------------------------------------------------------------------
            */

            $expense = Expense::create([

                'expense_category_id' =>
                    $expenseCategoryId,

                'amount' =>
                    $amount,

                'payment_method' =>
                    $paymentMethod,

                'expense_date' =>
                    now(),

                'reference_number' =>
                    $referenceNumber,

                'description' =>
                    $description,

                'created_by' =>
                    $createdBy,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction =
                AccountingTransaction::create([

                    'client_id' => null,

                    'booking_id' => null,

                    'payment_id' => null,

                    'type' => 'expense',

                    'category' => 'expense',

                    'payment_method' =>
                        $paymentMethod,

                    'amount' =>
                        $amount,

                    'reference_number' =>
                        $referenceNumber,

                    'description' =>
                        $description,

                    'transaction_date' =>
                        $expense->expense_date,

                    'status' =>
                        'completed',

                    /*
                     * UUID عملیات PWA
                     */
                    'offline_id' =>
                        $offlineId,

                    /*
                     * اگر از Offline Sync آمده باشد،
                     * زمان Sync ثبت می‌شود.
                     */
                    'synced_at' =>
                        $offlineId !== null
                            ? now()
                            : null,

                    'created_by' =>
                        $createdBy,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Connect Expense To Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $expense->update([
                'accounting_transaction_id' =>
                    $accountingTransaction->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Sync Cash Expense With Cash Register
            |--------------------------------------------------------------------------
            |
            | فقط هزینه نقدی از صندوق کم می‌شود.
            | POS / Bank Transfer / Other روی Cash Register اثری ندارند.
            |
            */

            if ($paymentMethod === 'cash') {

                app(CashRegisterService::class)
                    ->addExpense(
                        $accountingTransaction,
                        $expense->id,
                        $createdBy
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Refresh Expense
            |--------------------------------------------------------------------------
            */

            $expense->refresh();

            /*
            |--------------------------------------------------------------------------
            | Create Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(

                action: 'expense_created',

                entityType: 'expense',

                entityId:
                $expense->id,

                bookingId:
                null,

                clientId:
                null,

                description:
                $description
                ?? 'Expense created',

                oldValues:
                null,

                newValues: [

                    'expense_id' =>
                        $expense->id,

                    'expense_category_id' =>
                        $expense->expense_category_id,

                    'accounting_transaction_id' =>
                        $expense->accounting_transaction_id,

                    'amount' =>
                        (float) $expense->amount,

                    'payment_method' =>
                        $expense->payment_method,

                    'reference_number' =>
                        $expense->reference_number,

                    'description' =>
                        $expense->description,

                    'expense_date' =>
                        $expense->expense_date
                            ?->toDateTimeString(),

                    'offline_id' =>
                        $offlineId,
                ],

                userId:
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return Expense
            |--------------------------------------------------------------------------
            */

            return $expense->fresh();
        });
    }
}
