<?php

namespace App\Services;

use App\Models\OfflineSyncRequest;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class OfflineSyncService
{
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
            throw new RuntimeException('Offline ID is required.');
        }
        if ($action === '') {
            throw new RuntimeException('Offline action is required.');
        }

        $existing = OfflineSyncRequest::query()->where('offline_id', $offlineId)->first();
        if ($existing) {
            $this->validateDuplicateRequest($existing, $clientId, $userId, $action, $payload);
            // Idempotency: never execute the same offline_id twice from sync/batch.
            return $existing;
        }

        try {
            // offline_id MUST have a UNIQUE index in the database.
            $record = OfflineSyncRequest::create([
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
        } catch (QueryException $e) {
            // Handle concurrent insert of the same offline_id.
            $existing = OfflineSyncRequest::query()->where('offline_id', $offlineId)->first();
            if (!$existing) {
                throw $e;
            }
            $this->validateDuplicateRequest($existing, $clientId, $userId, $action, $payload);
            return $existing;
        }

        return $this->execute($record);
    }

    public function execute(OfflineSyncRequest $record): OfflineSyncRequest
    {
        try {
            return DB::transaction(function () use ($record) {
                $locked = OfflineSyncRequest::query()
                    ->whereKey($record->id)
                    ->lockForUpdate()
                    ->first();

                if (!$locked) {
                    throw new RuntimeException('Offline sync request not found.');
                }

                if ($locked->status === 'completed') {
                    return $locked;
                }

                if (!in_array($locked->status, ['pending', 'failed'], true)) {
                    throw new RuntimeException('Offline operation is already being processed.');
                }

                $locked->update([
                    'status' => 'processing',
                    'attempts' => ((int) $locked->attempts) + 1,
                    'error_message' => null,
                ]);

                $result = $this->processAction($locked);

                $locked->update([
                    'status' => 'completed',
                    'result' => $result,
                    'error_message' => null,
                    'processed_at' => now(),
                ]);

                return $locked->fresh();
            });
        } catch (Throwable $e) {
            // The business transaction rolled back. Acquire the row lock again
            // so a concurrent successful execution cannot be overwritten as failed.
            try {
                DB::transaction(function () use ($record, $e) {
                    $locked = OfflineSyncRequest::query()
                        ->whereKey($record->id)
                        ->lockForUpdate()
                        ->first();

                    if ($locked && in_array($locked->status, ['pending', 'failed'], true)) {
                        $locked->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    }
                });
            } catch (Throwable $markingError) {
                report($markingError);
            }

            throw $e;
        }
    }

    public function retry(OfflineSyncRequest $record): OfflineSyncRequest
    {
        $record->refresh();

        if ($record->status === 'completed') {
            return $record;
        }

        if ($record->status !== 'failed') {
            throw new RuntimeException('Only failed operations can be retried.');
        }

        return $this->execute($record);
    }

    private function processAction(OfflineSyncRequest $record): array
    {
        $createdBy = $record->user_id !== null ? (int) $record->user_id : null;

        return match ($record->action) {
            'payment_pos' => $this->processPosPayment($record, $createdBy),
            'payment_cash' => $this->processCashPayment($record, $createdBy),
            'expense_create' => $this->processExpenseCreate($record, $createdBy),
            'cash_in' => $this->processCashIn($record, $createdBy),
            'cash_out' => $this->processCashOut($record, $createdBy),
            'refund_create' => $this->processRefundCreate($record, $createdBy),
            default => throw new RuntimeException('Unsupported offline action: ' . $record->action),
        };
    }

    private function processPosPayment(OfflineSyncRequest $record, ?int $createdBy): array
    {
        $payload = $record->payload ?? [];
        $paymentId = $this->positiveId($payload, 'payment_id', 'Payment ID');
        $terminalId = $this->positiveId($payload, 'pos_terminal_id', 'POS terminal ID');
        $reference = $this->requiredString($payload, 'reference_number', 'POS reference number');
        $payment = $this->findPayment($paymentId, $record);

        $payment = app(PosPaymentService::class)->markAsPaidManually(
            payment: $payment,
            terminalId: $terminalId,
            referenceNumber: $reference,
            createdBy: $createdBy,
            offlineId: $record->offline_id
        );

        return [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'client_id' => $payment->client_id,
            'type' => $payment->type,
            'payment_method' => $payment->payment_method,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'reference_number' => $payment->reference_number,
            'offline_id' => $payment->offline_id,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
        ];
    }

    private function processCashPayment(OfflineSyncRequest $record, ?int $createdBy): array
    {
        $payload = $record->payload ?? [];
        $paymentId = $this->positiveId($payload, 'payment_id', 'Payment ID');
        $payment = $this->findPayment($paymentId, $record);

        $payment = app(PaymentService::class)->markAsPaidByCash(
            payment: $payment,
            createdBy: $createdBy,
            offlineId: $record->offline_id
        );

        return [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'client_id' => $payment->client_id,
            'type' => $payment->type,
            'payment_method' => $payment->payment_method,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'offline_id' => $payment->offline_id,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
        ];
    }

    private function processExpenseCreate(OfflineSyncRequest $record, ?int $createdBy): array
    {
        $payload = $record->payload ?? [];
        $categoryId = $this->positiveId($payload, 'expense_category_id', 'Expense category ID');
        $amount = $this->positiveAmount($payload, 'Expense amount');
        $method = $this->requiredString($payload, 'payment_method', 'Expense payment method');

        $expense = app(ExpenseService::class)->createExpense(
            expenseCategoryId: $categoryId,
            amount: $amount,
            paymentMethod: $method,
            referenceNumber: $this->optionalString($payload, 'reference_number'),
            description: $this->optionalString($payload, 'description'),
            createdBy: $createdBy,
            offlineId: $record->offline_id
        );

        return [
            'expense_id' => $expense->id,
            'expense_category_id' => $expense->expense_category_id,
            'accounting_transaction_id' => $expense->accounting_transaction_id,
            'amount' => (float) $expense->amount,
            'payment_method' => $expense->payment_method,
            'reference_number' => $expense->reference_number,
            'description' => $expense->description,
            'expense_date' => $expense->expense_date?->toDateTimeString(),
            'offline_id' => $record->offline_id,
        ];
    }

    private function processCashIn(OfflineSyncRequest $record, ?int $createdBy): array
    {
        $payload = $record->payload ?? [];
        $transaction = app(CashRegisterService::class)->addCashIn(
            amount: $this->positiveAmount($payload, 'Cash in amount'),
            referenceNumber: $this->optionalString($payload, 'reference_number'),
            description: $this->optionalString($payload, 'description'),
            createdBy: $createdBy,
            offlineId: $record->offline_id
        );

        return $this->formatCashTransaction($transaction);
    }

    private function processCashOut(OfflineSyncRequest $record, ?int $createdBy): array
    {
        $payload = $record->payload ?? [];
        $transaction = app(CashRegisterService::class)->addCashOut(
            amount: $this->positiveAmount($payload, 'Cash out amount'),
            referenceNumber: $this->optionalString($payload, 'reference_number'),
            description: $this->optionalString($payload, 'description'),
            createdBy: $createdBy,
            offlineId: $record->offline_id
        );

        return $this->formatCashTransaction($transaction);
    }

    private function processRefundCreate(OfflineSyncRequest $record, ?int $createdBy): array
    {
        $payload = $record->payload ?? [];
        $paymentId = $this->positiveId($payload, 'payment_id', 'Payment ID');
        $payment = $this->findPayment($paymentId, $record);
        $amount = $this->positiveAmount($payload, 'Refund amount');
        $method = $this->requiredString($payload, 'payment_method', 'Refund payment method');

        $refund = app(RefundService::class)->createRefund(
            payment: $payment,
            amount: $amount,
            paymentMethod: $method,
            referenceNumber: $this->optionalString($payload, 'reference_number'),
            description: $this->optionalString($payload, 'description'),
            createdBy: $createdBy,
            offlineId: $record->offline_id
        );

        return [
            'refund_payment_id' => $refund->id,
            'refunded_payment_id' => $refund->refunded_payment_id,
            'booking_id' => $refund->booking_id,
            'client_id' => $refund->client_id,
            'type' => $refund->type,
            'amount' => (float) $refund->amount,
            'payment_method' => $refund->payment_method,
            'status' => $refund->status,
            'reference_number' => $refund->reference_number,
            'offline_id' => $refund->offline_id,
            'paid_at' => $refund->paid_at?->toDateTimeString(),
        ];
    }

    private function formatCashTransaction($transaction): array
    {
        return [
            'cash_register_transaction_id' => $transaction->id,
            'cash_register_id' => $transaction->cash_register_id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'reference_number' => $transaction->reference_number,
            'description' => $transaction->description,
            'offline_id' => $transaction->offline_id,
            'transaction_date' => $transaction->transaction_date?->toDateTimeString(),
        ];
    }

    private function findPayment(int $paymentId, OfflineSyncRequest $record): Payment
    {
        $payment = Payment::query()->whereKey($paymentId)->first();
        if (!$payment) {
            throw new RuntimeException('Payment not found.');
        }

        if ($record->client_id !== null && (int) $payment->client_id !== (int) $record->client_id) {
            throw new RuntimeException('Payment does not belong to this client.');
        }

        return $payment;
    }

    private function positiveId(array $payload, string $key, string $label): int
    {
        $value = $payload[$key] ?? null;
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new RuntimeException($label . ' is required.');
        }
        return (int) $value;
    }

    private function positiveAmount(array $payload, string $label): float
    {
        $value = $payload['amount'] ?? null;
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
            throw new RuntimeException($label . ' must be greater than zero.');
        }
        return (float) $value;
    }

    private function requiredString(array $payload, string $key, string $label): string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException($label . ' is required.');
        }
        return $value;
    }

    private function optionalString(array $payload, string $key): ?string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        return $value === '' ? null : $value;
    }

    private function validateDuplicateRequest(
        OfflineSyncRequest $existing,
        ?int $clientId,
        ?int $userId,
        string $action,
        array $payload
    ): void {
        $storedPayload = $existing->payload;
        if (is_string($storedPayload)) {
            $storedPayload = json_decode($storedPayload, true, 512, JSON_THROW_ON_ERROR);
        }

        if (
            $existing->client_id !== $clientId ||
            ($existing->user_id === null ? null : (int) $existing->user_id) !== $userId ||
            $existing->action !== $action ||
            !is_array($storedPayload) ||
            $this->normalize($storedPayload) !== $this->normalize($payload)
        ) {
            throw new RuntimeException('Offline ID conflict: request data does not match the original request.');
        }
    }

    private function normalize(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->normalize($value);
            }
        }
        unset($value);

        if (!array_is_list($data)) {
            ksort($data);
        }
        return $data;
    }
}
