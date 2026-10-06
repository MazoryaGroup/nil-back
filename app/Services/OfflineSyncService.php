<?php

namespace App\Services;

use App\Models\OfflineSyncRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use App\Models\Payment;


class OfflineSyncService
{
    /**
     * Process one offline operation.
     */
    public function process(
        ?int $clientId,
        ?int $userId,
        string $offlineId,
        string $action,
        array $payload
    ): OfflineSyncRequest {

        $offlineId = trim($offlineId);
        $action = trim($action);

        if ($offlineId === '') {
            throw new RuntimeException(
                'Offline ID is required.'
            );
        }

        if ($action === '') {
            throw new RuntimeException(
                'Offline action is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Idempotency Check
        |--------------------------------------------------------------------------
        |
        | اگر این offline_id قبلاً به سرور رسیده باشد، عملیات دوباره اجرا
        | نمی‌شود و همان Sync Request قبلی برگردانده می‌شود.
        |
        */

        $existing = OfflineSyncRequest::query()
            ->where('offline_id', $offlineId)
            ->first();

        if ($existing) {
            return $existing;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Sync Request
        |--------------------------------------------------------------------------
        */

        try {
            $syncRequest = OfflineSyncRequest::create([
                'client_id' => $clientId,

                'user_id' => $userId,

                'offline_id' => $offlineId,

                'action' => $action,

                'payload' => $payload,

                'status' => 'pending',

                'result' => null,

                'error_message' => null,

                'attempts' => 0,

                'processed_at' => null,
            ]);
        } catch (Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Race Condition Protection
            |--------------------------------------------------------------------------
            |
            | اگر دو درخواست با offline_id یکسان تقریباً همزمان رسیدند،
            | UNIQUE دیتابیس اجازه Duplicate نمی‌دهد.
            |
            */

            $existing = OfflineSyncRequest::query()
                ->where('offline_id', $offlineId)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Sync
        |--------------------------------------------------------------------------
        */

        return $this->execute($syncRequest);
    }

    /**
     * Execute stored sync request.
     */
    public function execute(
        OfflineSyncRequest $syncRequest
    ): OfflineSyncRequest {

        if ($syncRequest->status === 'completed') {
            return $syncRequest;
        }

        try {

            return DB::transaction(function () use ($syncRequest) {

                /*
                |--------------------------------------------------------------------------
                | Lock Sync Request
                |--------------------------------------------------------------------------
                */

                $syncRequest = OfflineSyncRequest::query()
                    ->where('id', $syncRequest->id)
                    ->lockForUpdate()
                    ->first();

                if (!$syncRequest) {
                    throw new RuntimeException(
                        'Offline sync request not found.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Already Completed
                |--------------------------------------------------------------------------
                */

                if ($syncRequest->status === 'completed') {
                    return $syncRequest;
                }

                /*
                |--------------------------------------------------------------------------
                | Processing
                |--------------------------------------------------------------------------
                */

                $syncRequest->update([
                    'status' => 'processing',

                    'attempts' =>
                        ((int) $syncRequest->attempts) + 1,

                    'error_message' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Process Action
                |--------------------------------------------------------------------------
                */

                $result = $this->processAction(
                    $syncRequest
                );

                /*
                |--------------------------------------------------------------------------
                | Completed
                |--------------------------------------------------------------------------
                */

                $syncRequest->update([
                    'status' => 'completed',

                    'result' => $result,

                    'error_message' => null,

                    'processed_at' => now(),
                ]);

                return $syncRequest->fresh();
            });

        } catch (Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Mark As Failed
            |--------------------------------------------------------------------------
            */

            OfflineSyncRequest::query()
                ->where('id', $syncRequest->id)
                ->update([
                    'status' => 'failed',

                    'error_message' =>
                        $exception->getMessage(),
                ]);

            throw $exception;
        }
    }

    /**
     * Retry failed sync request.
     */
    public function retry(
        OfflineSyncRequest $syncRequest
    ): OfflineSyncRequest {

        if ($syncRequest->status === 'completed') {
            return $syncRequest;
        }

        return $this->execute(
            $syncRequest
        );
    }

    /**
     * Execute action.
     */
    /**
     * Execute offline action.
     */
    private function processAction(
        OfflineSyncRequest $syncRequest
    ): array {

        $createdBy = $syncRequest->user_id
            ? (int) $syncRequest->user_id
            : null;

        return match ($syncRequest->action) {

            'payment_pos' =>
            $this->processPosPayment(
                $syncRequest,
                $createdBy
            ),

            'payment_cash' =>
            $this->processCashPayment(
                $syncRequest,
                $createdBy
            ),
            'expense_create' =>
            $this->processExpenseCreate(
                $syncRequest,
                $createdBy
            ),
            'cash_in' =>
            $this->processCashIn(
                $syncRequest,
                $createdBy
            ),
            'cash_out' =>
            $this->processCashOut(
                $syncRequest,
                $createdBy
            ),
            'refund_create' =>
            $this->processRefundCreate(
                $syncRequest,
                $createdBy
            ),

            default => throw new RuntimeException(
                'Unsupported offline action: '
                . $syncRequest->action
            ),
        };
    }

    /**
     * Process POS payment created while PWA was offline.
     */
    private function processPosPayment(
        OfflineSyncRequest $syncRequest,
        ?int $createdBy = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Payload
        |--------------------------------------------------------------------------
        */

        $payload = $syncRequest->payload ?? [];

        /*
        |--------------------------------------------------------------------------
        | Validate Payment ID
        |--------------------------------------------------------------------------
        */

        $paymentId = (int) (
            $payload['payment_id'] ?? 0
        );

        if ($paymentId <= 0) {
            throw new RuntimeException(
                'Payment ID is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate POS Reference Number
        |--------------------------------------------------------------------------
        */

        $referenceNumber = trim(
            (string) (
                $payload['reference_number'] ?? ''
            )
        );

        if ($referenceNumber === '') {
            throw new RuntimeException(
                'POS reference number is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find Payment
        |--------------------------------------------------------------------------
        */

        $payment = Payment::query()
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            throw new RuntimeException(
                'Payment not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Client
        |--------------------------------------------------------------------------
        */

        if (
            $syncRequest->client_id !== null &&
            (int) $payment->client_id !==
            (int) $syncRequest->client_id
        ) {
            throw new RuntimeException(
                'Payment does not belong to this client.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Payment As Paid By POS
        |--------------------------------------------------------------------------
        */

        $payment = app(PaymentService::class)
            ->markAsPaidByPos(
                payment: $payment,

                referenceNumber:
                $referenceNumber,

                createdBy: $createdBy,

                offlineId:
                $syncRequest->offline_id
            );

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
            'payment_id' =>
                $payment->id,

            'booking_id' =>
                $payment->booking_id,

            'client_id' =>
                $payment->client_id,

            'type' =>
                $payment->type,

            'payment_method' =>
                $payment->payment_method,

            'amount' =>
                (float) $payment->amount,

            'status' =>
                $payment->status,

            'reference_number' =>
                $payment->reference_number,

            'offline_id' =>
                $payment->offline_id,

            'paid_at' =>
                $payment->paid_at
                    ?->toDateTimeString(),
        ];
    }
    /**
     * Process cash payment created while PWA was offline.
     */
    private function processCashPayment(
        OfflineSyncRequest $syncRequest,
        ?int $createdBy = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Payload
        |--------------------------------------------------------------------------
        */

        $payload = $syncRequest->payload ?? [];

        /*
        |--------------------------------------------------------------------------
        | Validate Payment ID
        |--------------------------------------------------------------------------
        */

        $paymentId = (int) (
            $payload['payment_id'] ?? 0
        );

        if ($paymentId <= 0) {
            throw new RuntimeException(
                'Payment ID is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find Payment
        |--------------------------------------------------------------------------
        */

        $payment = Payment::query()
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            throw new RuntimeException(
                'Payment not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Client
        |--------------------------------------------------------------------------
        */

        if (
            $syncRequest->client_id !== null &&
            (int) $payment->client_id !==
            (int) $syncRequest->client_id
        ) {
            throw new RuntimeException(
                'Payment does not belong to this client.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Payment As Paid By Cash
        |--------------------------------------------------------------------------
        */

        $payment = app(PaymentService::class)
            ->markAsPaidByCash(
                payment: $payment,

                createdBy: $createdBy,

                offlineId:
                $syncRequest->offline_id
            );

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
            'payment_id' =>
                $payment->id,

            'booking_id' =>
                $payment->booking_id,

            'client_id' =>
                $payment->client_id,

            'type' =>
                $payment->type,

            'payment_method' =>
                $payment->payment_method,

            'amount' =>
                (float) $payment->amount,

            'status' =>
                $payment->status,

            'offline_id' =>
                $payment->offline_id,

            'paid_at' =>
                $payment->paid_at
                    ?->toDateTimeString(),
        ];
    }
    /**
     * Process expense created while PWA was offline.
     */
    private function processExpenseCreate(
        OfflineSyncRequest $syncRequest,
        ?int $createdBy = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Payload
        |--------------------------------------------------------------------------
        */

        $payload = $syncRequest->payload ?? [];

        /*
        |--------------------------------------------------------------------------
        | Expense Category
        |--------------------------------------------------------------------------
        */

        $expenseCategoryId = (int) (
            $payload['expense_category_id'] ?? 0
        );

        if ($expenseCategoryId <= 0) {
            throw new RuntimeException(
                'Expense category ID is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        $amount = (float) (
            $payload['amount'] ?? 0
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'Expense amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        $paymentMethod = trim(
            (string) (
                $payload['payment_method'] ?? ''
            )
        );

        if ($paymentMethod === '') {
            throw new RuntimeException(
                'Expense payment method is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Optional Reference Number
        |--------------------------------------------------------------------------
        */

        $referenceNumber = isset(
            $payload['reference_number']
        )
            ? trim(
                (string) $payload['reference_number']
            )
            : null;

        if ($referenceNumber === '') {
            $referenceNumber = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Optional Description
        |--------------------------------------------------------------------------
        */

        $description = isset(
            $payload['description']
        )
            ? trim(
                (string) $payload['description']
            )
            : null;

        if ($description === '') {
            $description = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Expense
        |--------------------------------------------------------------------------
        */

        $expense = app(ExpenseService::class)
            ->createExpense(
                expenseCategoryId:
                $expenseCategoryId,

                amount:
                $amount,

                paymentMethod:
                $paymentMethod,

                referenceNumber:
                $referenceNumber,

                description:
                $description,

                createdBy:
                $createdBy,

                offlineId:
                $syncRequest->offline_id
            );

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
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
                $syncRequest->offline_id,
        ];
    }
    private function processCashIn(
        OfflineSyncRequest $syncRequest,
        ?int $createdBy = null
    ): array {

        $payload = $syncRequest->payload ?? [];

        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        $amount = (float) (
            $payload['amount'] ?? 0
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'Cash in amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reference Number
        |--------------------------------------------------------------------------
        */

        $referenceNumber = isset(
            $payload['reference_number']
        )
            ? trim(
                (string) $payload['reference_number']
            )
            : null;

        if ($referenceNumber === '') {
            $referenceNumber = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        $description = isset(
            $payload['description']
        )
            ? trim(
                (string) $payload['description']
            )
            : null;

        if ($description === '') {
            $description = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Add Cash
        |--------------------------------------------------------------------------
        */

        $transaction = app(
            CashRegisterService::class
        )->addCashIn(
            amount: $amount,
            referenceNumber: $referenceNumber,
            description: $description,
            createdBy: $createdBy,
            offlineId: $syncRequest->offline_id
        );

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
            'cash_register_transaction_id' =>
                $transaction->id,

            'cash_register_id' =>
                $transaction->cash_register_id,

            'type' =>
                $transaction->type,

            'amount' =>
                (float) $transaction->amount,

            'reference_number' =>
                $transaction->reference_number,

            'description' =>
                $transaction->description,

            'offline_id' =>
                $transaction->offline_id,

            'transaction_date' =>
                $transaction->transaction_date
                    ?->toDateTimeString(),
        ];
    }
    private function processCashOut(
        OfflineSyncRequest $syncRequest,
        ?int $createdBy = null
    ): array {

        $payload = $syncRequest->payload ?? [];

        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        $amount = (float) (
            $payload['amount'] ?? 0
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'Cash out amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reference Number
        |--------------------------------------------------------------------------
        */

        $referenceNumber = isset(
            $payload['reference_number']
        )
            ? trim(
                (string) $payload['reference_number']
            )
            : null;

        if ($referenceNumber === '') {
            $referenceNumber = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        $description = isset(
            $payload['description']
        )
            ? trim(
                (string) $payload['description']
            )
            : null;

        if ($description === '') {
            $description = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Cash Out
        |--------------------------------------------------------------------------
        */

        $transaction = app(
            CashRegisterService::class
        )->addCashOut(
            amount: $amount,
            referenceNumber: $referenceNumber,
            description: $description,
            createdBy: $createdBy,
            offlineId: $syncRequest->offline_id
        );

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
            'cash_register_transaction_id' =>
                $transaction->id,

            'cash_register_id' =>
                $transaction->cash_register_id,

            'type' =>
                $transaction->type,

            'amount' =>
                (float) $transaction->amount,

            'reference_number' =>
                $transaction->reference_number,

            'description' =>
                $transaction->description,

            'offline_id' =>
                $transaction->offline_id,

            'transaction_date' =>
                $transaction->transaction_date
                    ?->toDateTimeString(),
        ];
    }
    private function processRefundCreate(
        OfflineSyncRequest $syncRequest,
        ?int $createdBy = null
    ): array {

        $payload = $syncRequest->payload ?? [];

        /*
        |--------------------------------------------------------------------------
        | Original Payment
        |--------------------------------------------------------------------------
        */

        $paymentId = (int) (
            $payload['payment_id'] ?? 0
        );

        if ($paymentId <= 0) {
            throw new RuntimeException(
                'Payment ID is required.'
            );
        }

        $payment = Payment::query()
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            throw new RuntimeException(
                'Payment not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Client Validation
        |--------------------------------------------------------------------------
        */

        if (
            $syncRequest->client_id !== null &&
            (int) $payment->client_id !==
            (int) $syncRequest->client_id
        ) {
            throw new RuntimeException(
                'Payment does not belong to this client.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        $amount = (float) (
            $payload['amount'] ?? 0
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'Refund amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        $paymentMethod = trim(
            (string) (
                $payload['payment_method'] ?? ''
            )
        );

        if ($paymentMethod === '') {
            throw new RuntimeException(
                'Refund payment method is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reference Number
        |--------------------------------------------------------------------------
        */

        $referenceNumber = isset(
            $payload['reference_number']
        )
            ? trim(
                (string) $payload['reference_number']
            )
            : null;

        if ($referenceNumber === '') {
            $referenceNumber = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        $description = isset(
            $payload['description']
        )
            ? trim(
                (string) $payload['description']
            )
            : null;

        if ($description === '') {
            $description = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Refund
        |--------------------------------------------------------------------------
        */

        $refund = app(RefundService::class)
            ->createRefund(
                payment: $payment,
                amount: $amount,
                paymentMethod: $paymentMethod,
                referenceNumber: $referenceNumber,
                description: $description,
                createdBy: $createdBy,
                offlineId: $syncRequest->offline_id
            );

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
            'refund_payment_id' =>
                $refund->id,

            'refunded_payment_id' =>
                $refund->refunded_payment_id,

            'booking_id' =>
                $refund->booking_id,

            'client_id' =>
                $refund->client_id,

            'type' =>
                $refund->type,

            'amount' =>
                (float) $refund->amount,

            'payment_method' =>
                $refund->payment_method,

            'status' =>
                $refund->status,

            'reference_number' =>
                $refund->reference_number,

            'offline_id' =>
                $refund->offline_id,

            'paid_at' =>
                $refund->paid_at
                    ?->toDateTimeString(),
        ];
    }
}
