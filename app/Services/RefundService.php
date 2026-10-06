<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundService
{
    public function createRefund(
        Payment $payment,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Payment {

        /*
        |--------------------------------------------------------------------------
        | Validate Payment
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if ($amount <= 0) {
            throw new RuntimeException(
                'Refund amount must be greater than zero.'
            );
        }

        if ($amount > (float) $payment->amount) {
            throw new RuntimeException(
                'Refund amount cannot exceed the paid amount.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Payment Method
        |--------------------------------------------------------------------------
        */

        $allowedPaymentMethods = [
            'online',
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
                'Invalid refund payment method.'
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
        | Check Existing Offline Refund
        |--------------------------------------------------------------------------
        |
        | اگر PWA همان عملیات را دوباره Sync کرد،
        | Refund جدید ساخته نمی‌شود.
        |
        */

        if ($offlineId !== null) {

            $existingOfflinePayment = Payment::query()
                ->where(
                    'offline_id',
                    $offlineId
                )
                ->first();

            if ($existingOfflinePayment) {

                if ($existingOfflinePayment->type !== 'refund') {
                    throw new RuntimeException(
                        'Offline ID already belongs to another payment.'
                    );
                }

                return $existingOfflinePayment;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Refund
        |--------------------------------------------------------------------------
        */

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
            | Recheck Offline ID Inside Transaction
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {

                $existingOfflinePayment = Payment::query()
                    ->where(
                        'offline_id',
                        $offlineId
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existingOfflinePayment) {

                    if ($existingOfflinePayment->type !== 'refund') {
                        throw new RuntimeException(
                            'Offline ID already belongs to another payment.'
                        );
                    }

                    return $existingOfflinePayment;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Lock Original Payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->where(
                    'id',
                    $payment->id
                )
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException(
                    'Payment not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Original Payment
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Calculate Already Refunded Amount
            |--------------------------------------------------------------------------
            */

            $refundedAmount = (float) Payment::query()
                ->where(
                    'refunded_payment_id',
                    $payment->id
                )
                ->where(
                    'type',
                    'refund'
                )
                ->whereIn(
                    'status',
                    ['paid', 'refunded']
                )
                ->sum('amount');

            $refundableAmount = max(
                0,
                (float) $payment->amount
                - $refundedAmount
            );

            if ($amount > $refundableAmount) {
                throw new RuntimeException(
                    'Refund amount exceeds the remaining refundable amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate Refund Protection By Reference Number
            |--------------------------------------------------------------------------
            */

            if ($referenceNumber !== null) {

                $duplicateRefund = Payment::query()
                    ->where(
                        'refunded_payment_id',
                        $payment->id
                    )
                    ->where(
                        'type',
                        'refund'
                    )
                    ->where(
                        'reference_number',
                        $referenceNumber
                    )
                    ->whereIn(
                        'status',
                        ['paid', 'refunded']
                    )
                    ->exists();

                if ($duplicateRefund) {
                    throw new RuntimeException(
                        'This refund appears to have already been processed.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Values Before Refund For Audit
            |--------------------------------------------------------------------------
            */

            $oldValues = [

                'original_payment_id' =>
                    $payment->id,

                'original_payment_type' =>
                    $payment->type,

                'original_payment_amount' =>
                    (float) $payment->amount,

                'already_refunded_amount' =>
                    $refundedAmount,

                'refundable_amount' =>
                    $refundableAmount,
            ];

            /*
            |--------------------------------------------------------------------------
            | Create Refund Payment
            |--------------------------------------------------------------------------
            */

            $refundPayment = Payment::create([

                'booking_id' =>
                    $payment->booking_id,

                'refunded_payment_id' =>
                    $payment->id,

                'client_id' =>
                    $payment->client_id,

                'amount' =>
                    $amount,

                'type' =>
                    'refund',

                'payment_method' =>
                    $paymentMethod,

                'status' =>
                    'paid',

                'gateway' =>
                    null,

                'transaction_id' =>
                    null,

                'reference_number' =>
                    $referenceNumber,

                'authority' =>
                    null,

                /*
                 * UUID عملیات آفلاین PWA
                 */
                'offline_id' =>
                    $offlineId,

                'paid_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction =
                AccountingTransaction::create([

                    'client_id' =>
                        $payment->client_id,

                    'booking_id' =>
                        $payment->booking_id,

                    'payment_id' =>
                        $refundPayment->id,

                    'type' =>
                        'refund',

                    'category' =>
                        'service_refund',

                    'payment_method' =>
                        $paymentMethod,

                    'amount' =>
                        $amount,

                    'reference_number' =>
                        $referenceNumber,

                    'description' =>
                        $description
                        ?? 'Service payment refund - '
                        . $payment->type,

                    'transaction_date' =>
                        $refundPayment->paid_at
                        ?? now(),

                    'status' =>
                        'completed',

                    /*
                     * همان Offline ID روی Accounting
                     */
                    'offline_id' =>
                        $offlineId,

                    /*
                     * اگر عملیات از Offline Queue آمده باشد
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
            | Sync Cash Refund With Cash Register
            |--------------------------------------------------------------------------
            |
            | فقط Refund نقدی از صندوق کم می‌شود.
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
            | Recalculate Booking Financial Status
            |--------------------------------------------------------------------------
            */

            $booking = $payment
                ->booking()
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            app(PaymentService::class)
                ->recalculateBookingFinancialStatus(
                    $booking
                );

            /*
            |--------------------------------------------------------------------------
            | Refresh Models
            |--------------------------------------------------------------------------
            */

            $refundPayment->refresh();
            $booking->refresh();

            /*
            |--------------------------------------------------------------------------
            | Create Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(

                action:
                'refund_created',

                entityType:
                'payment',

                entityId:
                $refundPayment->id,

                bookingId:
                $refundPayment->booking_id,

                clientId:
                $refundPayment->client_id,

                description:
                $description
                ?? 'Refund created for payment #'
            . $payment->id,

                oldValues:
                $oldValues,

                newValues: [

                    'refund_payment_id' =>
                        $refundPayment->id,

                    'refunded_payment_id' =>
                        $payment->id,

                    'amount' =>
                        (float) $refundPayment->amount,

                    'payment_method' =>
                        $refundPayment->payment_method,

                    'status' =>
                        $refundPayment->status,

                    'reference_number' =>
                        $refundPayment->reference_number,

                    'offline_id' =>
                        $refundPayment->offline_id,

                    'paid_at' =>
                        $refundPayment
                            ->paid_at
                            ?->toDateTimeString(),

                    'total_refunded_amount' =>
                        $refundedAmount
                        + $amount,

                    'remaining_refundable_amount' =>
                        max(
                            0,
                            $refundableAmount
                            - $amount
                        ),

                    'booking_paid_amount' =>
                        (float) $booking->paid_amount,

                    'booking_payment_status' =>
                        $booking->payment_status,
                ],

                userId:
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return Refund Payment
            |--------------------------------------------------------------------------
            */

            return $refundPayment->fresh();
        });
    }
}
