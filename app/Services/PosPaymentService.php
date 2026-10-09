<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PosPaymentRequest;
use App\Models\PosTerminal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosPaymentService
{
    public function markAsPaidManually(
        Payment $payment,
        int $terminalId,
        string $referenceNumber,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Payment {
        $referenceNumber = trim($referenceNumber);
        $offlineId = $offlineId !== null
            ? trim($offlineId)
            : null;

        $offlineId = $offlineId === '' ? null : $offlineId;

        if ($referenceNumber === '') {
            throw new RuntimeException('POS reference number is required.');
        }

        return DB::transaction(function () use (
            $payment,
            $terminalId,
            $referenceNumber,
            $createdBy,
            $offlineId
        ) {
            // Always lock booking before payment.
            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $payment->booking_id !== (int) $booking->id) {
                throw new RuntimeException('Payment booking mismatch.');
            }
// Prevent manual POS settlement while an integrated POS
// transaction for this booking is unresolved.
            app(PaymentService::class)
                ->ensureNoUnresolvedPosPayment($booking);

            $terminal = PosTerminal::query()
                ->whereKey($terminalId)
                ->firstOrFail();

            if (!$terminal->is_active || $terminal->connection_type !== 'manual') {
                throw new RuntimeException('Manual POS terminal is not available.');
            }

            if (!in_array($payment->type, ['deposit', 'remaining'], true)) {
                throw new RuntimeException('Invalid payment type.');
            }

            // Strict idempotency.
            if ($payment->status === 'paid') {
                if (
                    $payment->payment_method === 'pos' &&
                    (int) $payment->pos_terminal_id === $terminalId &&
                    $payment->reference_number === $referenceNumber &&
                    ($offlineId === null || $payment->offline_id === $offlineId)
                ) {
                    return $payment->fresh();
                }

                throw new RuntimeException(
                    'Payment was already completed with different details.'
                );
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException('Payment is not pending.');
            }

            if ($booking->status === 'cancelled') {
                throw new RuntimeException('Cancelled booking cannot be paid.');
            }

            // Block unresolved online operations across this booking.
            $onlineInProgress = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNotNull('initiation_token')
                        ->orWhereNotNull('authority');
                })
                ->exists();

            if ($onlineInProgress) {
                throw new RuntimeException(
                    'An online payment requires reconciliation before POS settlement.'
                );
            }

            // Never overwrite an existing gateway or transaction.
            if (
                !empty($payment->gateway) ||
                !empty($payment->authority) ||
                !empty($payment->initiation_token) ||
                !empty($payment->transaction_id) ||
                !empty($payment->reference_number) ||
                !empty($payment->offline_id) ||
                !empty($payment->pos_terminal_id)
            ) {
                throw new RuntimeException(
                    'Existing payment information requires review.'
                );
            }

            // Integrated POS may have an unknown result.
            $unresolvedPos = PosPaymentRequest::query()
                ->whereHas('payment', function ($query) use ($booking) {
                    $query->where('booking_id', $booking->id);
                })
                ->whereIn('status', [
                    'pending',
                    'processing',
                    'unknown',
                ])
                ->exists();

            if ($unresolvedPos) {
                throw new RuntimeException(
                    'An integrated POS transaction requires reconciliation.'
                );
            }

            $remaining = app(PaymentService::class)
                ->getBookingRemainingAmount($booking);

            if ($remaining <= 0) {
                throw new RuntimeException('Booking has no remaining balance.');
            }

            if (
                $payment->type === 'remaining' &&
                abs((float) $payment->amount - $remaining) > 0.001
            ) {
                throw new RuntimeException('Remaining amount mismatch.');
            }

            if (
                $payment->type === 'deposit' &&
                (
                    (float) $payment->amount > $remaining + 0.001 ||
                    (float) $payment->amount <= 0
                )
            ) {
                throw new RuntimeException('Invalid deposit amount.');
            }

            $duplicateReference = Payment::query()
                ->where('payment_method', 'pos')
                ->where('pos_terminal_id', $terminalId)
                ->where('reference_number', $referenceNumber)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicateReference) {
                throw new RuntimeException('POS receipt already registered.');
            }

            if (
                $offlineId !== null &&
                Payment::query()
                    ->where('offline_id', $offlineId)
                    ->where('id', '!=', $payment->id)
                    ->exists()
            ) {
                throw new RuntimeException('Offline operation already registered.');
            }

            if (
                AccountingTransaction::query()
                    ->where('payment_id', $payment->id)
                    ->where('type', 'income')
                    ->exists()
            ) {
                throw new RuntimeException(
                    'Existing accounting transaction requires review.'
                );
            }

            $oldValues = $payment->only([
                'status',
                'payment_method',
                'reference_number',
                'pos_terminal_id',
                'offline_id',
            ]);

            $payment->update([
                'payment_method' => 'pos',
                'pos_terminal_id' => $terminalId,
                'status' => 'paid',
                'gateway' => null,
                'transaction_id' => null,
                'authority' => null,
                'reference_number' => $referenceNumber,
                'offline_id' => $offlineId,
                'paid_at' => now(),
            ]);

            AccountingTransaction::create([
                'client_id' => $payment->client_id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'type' => 'income',
                'category' => 'service_payment',
                'payment_method' => 'pos',
                'amount' => $payment->amount,
                'reference_number' => $referenceNumber,
                'description' => 'Service payment - '
                    . $payment->type
                    . ' via manual POS #'
                    . $terminalId,
                'transaction_date' => $payment->paid_at,
                'status' => 'completed',
                'offline_id' => $offlineId,
                'synced_at' => $offlineId !== null ? now() : null,
                'created_by' => $createdBy,
            ]);

            // POS is not physical cash.
            // Do not call CashRegisterService.

            app(PaymentService::class)
                ->recalculateBookingFinancialStatus($booking);

            $payment->refresh();

            app(AccountingAuditService::class)->log(
                action: 'payment_paid',
                entityType: 'payment',
                entityId: $payment->id,
                bookingId: $booking->id,
                clientId: $payment->client_id,
                description: 'Manual POS payment completed',
                oldValues: $oldValues,
                newValues: [
                    'status' => $payment->status,
                    'payment_method' => 'pos',
                    'pos_terminal_id' => $terminalId,
                    'reference_number' => $referenceNumber,
                    'amount' => (float) $payment->amount,
                ],
                userId: $createdBy
            );

            return $payment->fresh();
        }, 3);
    }
}
