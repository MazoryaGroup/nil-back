<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundService
{
    /*
    |--------------------------------------------------------------------------
    | Create Refund
    |--------------------------------------------------------------------------
    |
    | General refund method.
    | Used for normal refunds, including cancellation refunds.
    | Does not impose an overpayment-only limit.
    |
    */

    public function createRefund(
        Payment $payment,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Payment {

        $this->validateAmount($amount);
        $this->validatePaymentMethod($paymentMethod);

        $referenceNumber = $this->normalizeNullableString(
            $referenceNumber
        );

        $offlineId = $this->normalizeNullableString(
            $offlineId
        );

        return DB::transaction(function () use (
            $payment,
            $amount,
            $paymentMethod,
            $referenceNumber,
            $description,
            $createdBy,
            $offlineId
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1. Lock Booking First
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | 2. Lock Original Payment Second
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $payment->booking_id !== (int) $booking->id) {
                throw new RuntimeException(
                    'Payment booking mismatch.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Validate Original Payment
            |--------------------------------------------------------------------------
            */

            $this->validateOriginalPayment($payment);

            /*
            |--------------------------------------------------------------------------
            | 4. Check Offline Idempotency
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {

                $existingOfflinePayment = Payment::query()
                    ->where('offline_id', $offlineId)
                    ->lockForUpdate()
                    ->first();

                if ($existingOfflinePayment) {

                    if (
                        $existingOfflinePayment->type !== 'refund'
                        || (int) $existingOfflinePayment->refunded_payment_id
                        !== (int) $payment->id
                        || abs(
                            (float) $existingOfflinePayment->amount
                            - $amount
                        ) > 0.001
                        || $existingOfflinePayment->payment_method !== $paymentMethod
                    ) {
                        throw new RuntimeException(
                            'Offline ID already belongs to another operation.'
                        );
                    }

                    return $existingOfflinePayment;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Calculate Already Refunded Amount
            |--------------------------------------------------------------------------
            */

            $refundedAmount = (float) Payment::query()
                ->where('refunded_payment_id', $payment->id)
                ->where('type', 'refund')
                ->whereIn('status', ['paid', 'refunded'])
                ->sum('amount');

            $refundableAmount = max(
                0,
                round(
                    (float) $payment->amount - $refundedAmount,
                    2
                )
            );

            /*
            |--------------------------------------------------------------------------
            | 6. Validate Refund Amount
            |--------------------------------------------------------------------------
            */

            if ($amount > $refundableAmount + 0.001) {
                throw new RuntimeException(
                    'Refund amount exceeds the remaining refundable amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Check Duplicate Reference
            |--------------------------------------------------------------------------
            */

            if ($referenceNumber !== null) {

                $duplicateRefund = Payment::query()
                    ->where('refunded_payment_id', $payment->id)
                    ->where('type', 'refund')
                    ->where('reference_number', $referenceNumber)
                    ->exists();

                if ($duplicateRefund) {
                    throw new RuntimeException(
                        'This refund reference has already been registered.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 8. Prepare Audit Values
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'original_payment_id' => $payment->id,
                'original_payment_type' => $payment->type,
                'original_payment_amount' => (float) $payment->amount,
                'already_refunded_amount' => $refundedAmount,
                'refundable_amount' => $refundableAmount,
                'booking_paid_amount' => (float) $booking->paid_amount,
                'booking_payment_status' => $booking->payment_status,
            ];

            /*
            |--------------------------------------------------------------------------
            | 9. Create Refund Payment
            |--------------------------------------------------------------------------
            |
            | A refund is registered as paid only AFTER actual
            | repayment has been confirmed by the operator.
            |
            */

            $refundPayment = Payment::create([
                'booking_id' => $booking->id,
                'refunded_payment_id' => $payment->id,
                'client_id' => $payment->client_id,
                'amount' => $amount,
                'type' => 'refund',
                'payment_method' => $paymentMethod,
                'status' => 'paid',
                'gateway' => null,
                'transaction_id' => null,
                'reference_number' => $referenceNumber,
                'authority' => null,
                'offline_id' => $offlineId,
                'paid_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 10. Create Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction = AccountingTransaction::create([
                'client_id' => $payment->client_id,
                'booking_id' => $booking->id,
                'payment_id' => $refundPayment->id,
                'type' => 'refund',
                'category' => 'service_refund',
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'reference_number' => $referenceNumber,
                'description' => $description
                    ?? 'Service payment refund - ' . $payment->type,
                'transaction_date' => $refundPayment->paid_at,
                'status' => 'completed',
                'offline_id' => $offlineId,
                'synced_at' => $offlineId !== null ? now() : null,
                'created_by' => $createdBy,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 11. Sync Cash Refund
            |--------------------------------------------------------------------------
            |
            | Only physical cash refunds affect the cash register.
            | POS, online, and bank transfers do not directly
            | modify the physical cash register.
            |
            */

            if ($paymentMethod === 'cash') {

                app(CashRegisterService::class)
                    ->addRefund(
                        $accountingTransaction,
                        $createdBy
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | 12. Recalculate Booking Financial Status
            |--------------------------------------------------------------------------
            |
            | Booking is already locked.
            | Do not acquire the booking lock again.
            |
            */

            app(PaymentService::class)
                ->recalculateBookingFinancialStatus($booking);

            $booking->refresh();
            $refundPayment->refresh();

            /*
            |--------------------------------------------------------------------------
            | 13. Accounting Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(
                action: 'refund_created',
                entityType: 'payment',
                entityId: $refundPayment->id,
                bookingId: $booking->id,
                clientId: $refundPayment->client_id,
                description: $description
                ?? 'Refund created for payment #' . $payment->id,
                oldValues: $oldValues,
                newValues: [
                    'refund_payment_id' => $refundPayment->id,
                    'refunded_payment_id' => $payment->id,
                    'amount' => (float) $refundPayment->amount,
                    'payment_method' => $refundPayment->payment_method,
                    'status' => $refundPayment->status,
                    'reference_number' => $refundPayment->reference_number,
                    'offline_id' => $refundPayment->offline_id,
                    'paid_at' => $refundPayment->paid_at
                        ?->toDateTimeString(),
                    'total_refunded_amount' => $refundedAmount + $amount,
                    'remaining_refundable_amount' => max(
                        0,
                        $refundableAmount - $amount
                    ),
                    'booking_paid_amount' => (float) $booking->paid_amount,
                    'booking_payment_status' => $booking->payment_status,
                ],
                userId: $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | 14. Return Refund
            |--------------------------------------------------------------------------
            */

            return $refundPayment->fresh();

        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Overpayment Refund
    |--------------------------------------------------------------------------
    |
    | Refund only the amount paid in excess of the final
    | booking total.
    |
    | This method should only be called AFTER the money
    | has actually been returned to the customer.
    |
    */

    public function createOverpaymentRefund(
        Payment $payment,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $createdBy = null
    ): Payment {

        $this->validateAmount($amount);
        $this->validatePaymentMethod($paymentMethod);

        return DB::transaction(function () use (
            $payment,
            $amount,
            $paymentMethod,
            $referenceNumber,
            $description,
            $createdBy
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1. Lock Booking
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | 2. Lock Original Payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $payment->booking_id !== (int) $booking->id) {
                throw new RuntimeException(
                    'Payment booking mismatch.'
                );
            }

            $this->validateOriginalPayment($payment);

            /*
            |--------------------------------------------------------------------------
            | 3. Calculate Successful Payments
            |--------------------------------------------------------------------------
            */

            $totalPaid = (float) Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'paid')
                ->whereIn('type', [
                    'deposit',
                    'remaining',
                ])
                ->sum('amount');

            /*
            |--------------------------------------------------------------------------
            | 4. Calculate Previous Refunds
            |--------------------------------------------------------------------------
            */

            $totalRefunded = (float) Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'refund')
                ->whereIn('status', [
                    'paid',
                    'refunded',
                ])
                ->sum('amount');

            /*
            |--------------------------------------------------------------------------
            | 5. Calculate Net Paid
            |--------------------------------------------------------------------------
            */

            $netPaid = max(
                0,
                round($totalPaid - $totalRefunded, 2)
            );

            /*
            |--------------------------------------------------------------------------
            | 6. Calculate Overpayment
            |--------------------------------------------------------------------------
            */

            $overpaidAmount = max(
                0,
                round(
                    $netPaid - (float) $booking->total_amount,
                    2
                )
            );

            /*
            |--------------------------------------------------------------------------
            | 7. Validate Overpayment
            |--------------------------------------------------------------------------
            */

            if ($overpaidAmount <= 0) {
                throw new RuntimeException(
                    'This booking has no overpayment to refund.'
                );
            }

            if ($amount > $overpaidAmount + 0.001) {
                throw new RuntimeException(
                    'Refund exceeds the booking overpayment amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 8. Check Original Payment Refundable Balance
            |--------------------------------------------------------------------------
            */

            $alreadyRefunded = (float) Payment::query()
                ->where('refunded_payment_id', $payment->id)
                ->where('type', 'refund')
                ->whereIn('status', ['paid', 'refunded'])
                ->sum('amount');

            $paymentRefundable = max(
                0,
                round(
                    (float) $payment->amount - $alreadyRefunded,
                    2
                )
            );

            if ($amount > $paymentRefundable + 0.001) {
                throw new RuntimeException(
                    'Refund exceeds the refundable balance of the selected payment.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 9. Use Existing Refund Logic
            |--------------------------------------------------------------------------
            |
            | The existing method handles accounting,
            | cash register, audit, and financial recalculation.
            |
            */

            return $this->createRefund(
                payment: $payment,
                amount: $amount,
                paymentMethod: $paymentMethod,
                referenceNumber: $referenceNumber,
                description: $description
                ?? 'Booking overpayment refund',
                createdBy: $createdBy
            );

        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Original Payment
    |--------------------------------------------------------------------------
    */

    private function validateOriginalPayment(Payment $payment): void
    {
        if (!in_array(
            $payment->type,
            ['deposit', 'remaining'],
            true
        )) {
            throw new RuntimeException(
                'Only deposit or remaining payments can be refunded.'
            );
        }

        if ($payment->status !== 'paid') {
            throw new RuntimeException(
                'Only paid payments can be refunded.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Amount
    |--------------------------------------------------------------------------
    */

    private function validateAmount(float $amount): void
    {
        if (
            !is_finite($amount)
            || $amount <= 0
            || abs($amount - round($amount, 2)) > 0.000001
        ) {
            throw new RuntimeException(
                'Refund amount must be positive and have at most two decimal places.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Payment Method
    |--------------------------------------------------------------------------
    */

    private function validatePaymentMethod(string $paymentMethod): void
    {
        $allowedMethods = [
            'online',
            'cash',
            'pos',
            'bank_transfer',
            'other',
        ];

        if (!in_array(
            $paymentMethod,
            $allowedMethods,
            true
        )) {
            throw new RuntimeException(
                'Invalid refund payment method.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Nullable String
    |--------------------------------------------------------------------------
    */

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
