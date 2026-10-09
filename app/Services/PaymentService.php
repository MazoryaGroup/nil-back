<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\AccountingTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /*
    |--------------------------------------------------------------------------
    | Get Booking Remaining Amount
    |--------------------------------------------------------------------------
    */

    public function getBookingRemainingAmount(
        Booking $booking
    ): float {
        $paidAmount = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->whereIn('type', ['deposit', 'remaining'])
            ->sum('amount');

        $refundedAmount = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', 'refund')
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount');

        $netPaidAmount = max(
            0,
            (float) $paidAmount - (float) $refundedAmount
        );

        $remainingAmount = (float) $booking->total_amount
            - $netPaidAmount;

        return max(0, $remainingAmount);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Remaining Payment
    |--------------------------------------------------------------------------
    */


    public function createRemainingPayment(
        Booking $booking,
                $client
    ): Payment {
        if (!$client || (int) $booking->client_id !== (int) $client->id) {
            throw new RuntimeException(
                'Booking does not belong to this client.'
            );
        }

        return DB::transaction(function () use ($booking, $client) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureNoUnresolvedPosPayment($booking);

            if ((int) $booking->client_id !== (int) $client->id) {
                throw new RuntimeException(
                    'Booking does not belong to this client.'
                );
            }

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            $remainingAmount = $this->getBookingRemainingAmount($booking);

            if ($remainingAmount <= 0) {
                throw new RuntimeException(
                    'This booking has no remaining balance.'
                );
            }

            // بررسی تمام پرداخت‌های در انتظار، نه فقط آخرین مورد
            $pendingPayments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'remaining')
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // لینک زرین‌پال موجود نباید بازنویسی شود.
            $hasActiveOnlinePayment = $pendingPayments->contains(
                fn (Payment $payment) =>
                    $payment->gateway === 'zarinpal'
                    && !empty($payment->authority)
            );

            if ($hasActiveOnlinePayment) {
                throw new RuntimeException(
                    'An online payment is already in progress for this booking.'
                );
            }

            // اگر بیش از یک پرداخت pending وجود دارد،
            // بدون تعیین تکلیف آن‌ها پرداخت جدید ایجاد نکن.
            if ($pendingPayments->count() > 1) {
                throw new RuntimeException(
                    'Multiple pending payments exist. Please review them first.'
                );
            }

            $existingPayment = $pendingPayments->first();
            if (!empty($existingPayment->initiation_token)) {
                throw new RuntimeException(
                    'An online payment initiation is unresolved.'
                );
            }
            if ($existingPayment) {
                // هیچ پرداخت دارای شناسه درگاه یا تراکنش
                // نباید به صورت خودکار بازنویسی شود.
                if (
                    !empty($existingPayment->authority)
                    || !empty($existingPayment->transaction_id)
                    || !empty($existingPayment->reference_number)
                    || !empty($existingPayment->offline_id)
                ) {
                    throw new RuntimeException(
                        'Existing payment must be reviewed before retrying.'
                    );
                }

                $existingPayment->update([
                    'client_id' => $client->id,
                    'amount' => $remainingAmount,
                    'payment_method' => 'online',
                    'gateway' => null,
                    'paid_at' => null,
                ]);

                return $existingPayment->fresh();
            }

            return Payment::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'amount' => $remainingAmount,
                'type' => 'remaining',
                'payment_method' => 'online',
                'status' => 'pending',
                'gateway' => null,
                'transaction_id' => null,
                'reference_number' => null,
                'authority' => null,
                'offline_id' => null,
                'paid_at' => null,
            ]);
        }, 3);
    }


    /*
    |--------------------------------------------------------------------------
    | Create Deposit Payment
    |--------------------------------------------------------------------------
    */

    public function createDepositPayment(
        Booking $booking,
                $client
    ): Payment {

        if (
            !$client ||
            (int) $booking->client_id !== (int) $client->id
        ) {
            throw new RuntimeException(
                'Booking does not belong to this client.'
            );
        }

        return DB::transaction(function () use ($booking, $client) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureNoUnresolvedPosPayment($booking);

            if ((int) $booking->client_id !== (int) $client->id) {
                throw new RuntimeException(
                    'Booking does not belong to this client.'
                );
            }

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            if ($booking->status === 'completed') {
                throw new RuntimeException(
                    'Completed booking cannot be paid.'
                );
            }

            $depositAmount = (float) $booking->deposit_amount;

            if ($depositAmount <= 0) {
                throw new RuntimeException(
                    'This booking does not require a deposit.'
                );
            }

            $depositPayments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'deposit')
                ->whereIn('status', ['pending', 'paid'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (
                $depositPayments->contains(
                    fn (Payment $payment) => $payment->status === 'paid'
                )
            ) {
                throw new RuntimeException(
                    'Booking deposit has already been paid.'
                );
            }

            $pendingPayments = $depositPayments
                ->where('status', 'pending');

            if ($pendingPayments->count() > 1) {
                throw new RuntimeException(
                    'Multiple pending deposit payments require review.'
                );
            }

            $existingPayment = $pendingPayments->first();

            if ($existingPayment) {

                if (!empty($existingPayment->initiation_token)) {
                    throw new RuntimeException(
                        'Deposit payment initiation is unresolved.'
                    );
                }

                if (
                    !empty($existingPayment->authority) ||
                    !empty($existingPayment->transaction_id) ||
                    !empty($existingPayment->reference_number) ||
                    !empty($existingPayment->offline_id)
                ) {
                    throw new RuntimeException(
                        'Existing deposit payment requires review.'
                    );
                }

                $existingPayment->update([
                    'client_id' => $client->id,
                    'amount' => $depositAmount,
                    'payment_method' => 'online',
                    'gateway' => null,
                    'paid_at' => null,
                ]);

                return $existingPayment->fresh();
            }

            return Payment::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'amount' => $depositAmount,
                'type' => 'deposit',
                'payment_method' => 'online',
                'status' => 'pending',
                'gateway' => null,
                'transaction_id' => null,
                'reference_number' => null,
                'authority' => null,
                'offline_id' => null,
                'paid_at' => null,
            ]);

        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Deposit As Paid
    |--------------------------------------------------------------------------
    */


    public function markDepositAsPaid(
        Payment $payment,
        string $gateway,
        string $transactionId,
        float $verifiedAmount,
        ?int $createdBy = null
    ): Payment {

        $gateway = trim($gateway);
        $transactionId = trim($transactionId);

        if ($gateway === '' || $transactionId === '') {
            throw new RuntimeException(
                'Gateway and transaction ID are required.'
            );
        }

        if ($verifiedAmount <= 0) {
            throw new RuntimeException(
                'Verified amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $payment,
            $gateway,
            $transactionId,
            $verifiedAmount,
            $createdBy
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Booking First
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Lock Payment Second
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (
                !$payment ||
                (int) $payment->booking_id !== (int) $booking->id
            ) {
                throw new RuntimeException('Payment not found.');
            }

            if ($payment->type !== 'deposit') {
                throw new RuntimeException(
                    'This payment is not a deposit payment.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Idempotency
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {
                if (
                    $payment->gateway !== $gateway ||
                    $payment->transaction_id !== $transactionId
                ) {
                    throw new RuntimeException(
                        'Payment was already completed with another transaction.'
                    );
                }

                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be marked as paid.'
                );
            }

            if (
                $payment->gateway !== $gateway ||
                empty($payment->authority)
            ) {
                throw new RuntimeException(
                    'Payment gateway information is invalid.'
                );
            }
            if (!empty($payment->initiation_token)) {
                throw new RuntimeException(
                    'Payment initiation has not been completed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Verified Amount
            |--------------------------------------------------------------------------
            */

            if (
                abs((float) $payment->amount - $verifiedAmount) > 0.001
            ) {
                throw new RuntimeException(
                    'Verified amount does not match payment amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Booking Financial State
            |--------------------------------------------------------------------------
            */

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            $remainingAmount = $this->getBookingRemainingAmount($booking);

            if (
                $remainingAmount <= 0 ||
                (float) $payment->amount > $remainingAmount + 0.001
            ) {
                throw new RuntimeException(
                    'Deposit exceeds the current outstanding balance.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Transaction
            |--------------------------------------------------------------------------
            */

            $duplicate = Payment::query()
                ->where('gateway', $gateway)
                ->where('transaction_id', $transactionId)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    'Transaction ID has already been registered.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values For Audit
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'gateway' => $payment->gateway,
                'transaction_id' => $payment->transaction_id,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ];

            /*
            |--------------------------------------------------------------------------
            | Mark Payment As Paid
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'payment_method' => 'online',
                'status' => 'paid',
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction = AccountingTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'income')
                ->lockForUpdate()
                ->first();

            if (!$accountingTransaction) {
                $accountingTransaction = AccountingTransaction::create([
                    'client_id' => $payment->client_id,
                    'booking_id' => $payment->booking_id,
                    'payment_id' => $payment->id,
                    'type' => 'income',
                    'category' => 'service_payment',
                    'payment_method' => 'online',
                    'amount' => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'description' => 'Service payment - deposit',
                    'transaction_date' => $payment->paid_at ?? now(),
                    'status' => 'completed',
                    'offline_id' => null,
                    'synced_at' => null,
                    'created_by' => $createdBy,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Cash Register
            |--------------------------------------------------------------------------
            */

            $this->syncCashRegister($accountingTransaction);

            /*
            |--------------------------------------------------------------------------
            | Recalculate Booking
            |--------------------------------------------------------------------------
            */

            $this->recalculateBookingFinancialStatus($booking);

            $payment->refresh();

            /*
            |--------------------------------------------------------------------------
            | Payment Success Notification
            |--------------------------------------------------------------------------
            */

            $this->createPaymentSuccessNotification($payment);

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(
                action: 'payment_paid',
                entityType: 'payment',
                entityId: $payment->id,
                bookingId: $payment->booking_id,
                clientId: $payment->client_id,
                description: 'Deposit payment paid online',
                oldValues: $oldValues,
                newValues: [
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'gateway' => $payment->gateway,
                    'transaction_id' => $payment->transaction_id,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'amount' => (float) $payment->amount,
                ],
                userId: $createdBy
            );

            return $payment->fresh();

        }, 3);
    }


    /*
    |--------------------------------------------------------------------------
    | Mark Remaining As Paid
    |--------------------------------------------------------------------------
    */


    public function markRemainingAsPaid(
        Payment $payment,
        string $gateway,
        string $transactionId,
        float $verifiedAmount,
        ?int $createdBy = null
    ): Payment {

        $gateway = trim($gateway);
        $transactionId = trim($transactionId);

        if ($gateway === '' || $transactionId === '') {
            throw new RuntimeException(
                'Gateway and transaction ID are required.'
            );
        }

        if ($verifiedAmount <= 0) {
            throw new RuntimeException(
                'Verified amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $payment,
            $gateway,
            $transactionId,
            $verifiedAmount,
            $createdBy
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Booking First
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Lock Payment Second
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment || (int) $payment->booking_id !== (int) $booking->id) {
                throw new RuntimeException('Payment not found.');
            }

            if ($payment->type !== 'remaining') {
                throw new RuntimeException(
                    'This payment is not a remaining payment.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Idempotency
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {
                if (
                    $payment->gateway !== $gateway ||
                    $payment->transaction_id !== $transactionId
                ) {
                    throw new RuntimeException(
                        'Payment has already been completed with another transaction.'
                    );
                }

                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be marked as paid.'
                );
            }

            if (
                $payment->gateway !== $gateway ||
                empty($payment->authority)
            ) {
                throw new RuntimeException(
                    'Payment gateway information is invalid.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Amount
            |--------------------------------------------------------------------------
            */

            if (abs((float) $payment->amount - $verifiedAmount) > 0.001) {
                throw new RuntimeException(
                    'Verified amount does not match payment amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Transaction
            |--------------------------------------------------------------------------
            */

            $duplicate = Payment::query()
                ->where('gateway', $gateway)
                ->where('transaction_id', $transactionId)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    'Transaction ID has already been registered.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Booking
            |--------------------------------------------------------------------------
            */

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Verify Current Balance
            |--------------------------------------------------------------------------
            */

            $remainingAmount = $this->getBookingRemainingAmount($booking);

            if (
                $remainingAmount <= 0 ||
                abs((float) $payment->amount - $remainingAmount) > 0.001
            ) {
                throw new RuntimeException(
                    'Payment amount does not match the current booking balance.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Audit Old Values
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'gateway' => $payment->gateway,
                'transaction_id' => $payment->transaction_id,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ];

            /*
            |--------------------------------------------------------------------------
            | Mark Payment Paid
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'payment_method' => 'online',
                'status' => 'paid',
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction = AccountingTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'income')
                ->lockForUpdate()
                ->first();

            if (!$accountingTransaction) {
                $accountingTransaction = AccountingTransaction::create([
                    'client_id' => $payment->client_id,
                    'booking_id' => $payment->booking_id,
                    'payment_id' => $payment->id,
                    'type' => 'income',
                    'category' => 'service_payment',
                    'payment_method' => 'online',
                    'amount' => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'description' => 'Service payment - remaining',
                    'transaction_date' => $payment->paid_at ?? now(),
                    'status' => 'completed',
                    'offline_id' => null,
                    'synced_at' => null,
                    'created_by' => $createdBy,
                ]);
            }

            $this->syncCashRegister($accountingTransaction);

            $this->recalculateBookingFinancialStatus($booking);

            $payment->refresh();

            $this->createPaymentSuccessNotification($payment);

            app(AccountingAuditService::class)->log(
                action: 'payment_paid',
                entityType: 'payment',
                entityId: $payment->id,
                bookingId: $payment->booking_id,
                clientId: $payment->client_id,
                description: 'Remaining payment paid online',
                oldValues: $oldValues,
                newValues: [
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'gateway' => $payment->gateway,
                    'transaction_id' => $payment->transaction_id,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'amount' => (float) $payment->amount,
                ],
                userId: $createdBy
            );

            return $payment->fresh();

        }, 3);
    }

    /*
 |--------------------------------------------------------------------------
 | Mark Payment As Paid By POS
 |--------------------------------------------------------------------------
 */

    /**
     * Legacy POS payment method.
     *
     * @deprecated Use PosPaymentService::markAsPaidManually() instead.
     *
     * This method is intentionally disabled to prevent
     * bypassing POS terminal validation, payment checks,
     * idempotency protection, and accounting safeguards.
     */
    public function markAsPaidByPos(
        Payment $payment,
        string $referenceNumber,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Payment {
        throw new \RuntimeException(
            'Legacy POS payment method is disabled. '
            . 'Use PosPaymentService::markAsPaidManually() instead.'
        );
    }
    /*
|--------------------------------------------------------------------------
| Mark Payment As Paid By Cash
|--------------------------------------------------------------------------
*/


    public function markAsPaidByCash(
        Payment $payment,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Payment {

        $offlineId = $offlineId !== null
            ? trim($offlineId)
            : null;

        $offlineId = $offlineId === '' ? null : $offlineId;

        return DB::transaction(function () use (
            $payment,
            $createdBy,
            $offlineId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Booking First
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Lock Payment Second
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment || (int) $payment->booking_id !== (int) $booking->id) {
                throw new RuntimeException('Payment not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Payment
            |--------------------------------------------------------------------------
            */

            if (!in_array($payment->type, ['deposit', 'remaining'], true)) {
                throw new RuntimeException(
                    'Only deposit or remaining payments can be paid by cash.'
                );
            }

            if ($payment->status === 'paid') {
                if ($payment->payment_method !== 'cash') {
                    throw new RuntimeException(
                        'This payment was already paid using another method.'
                    );
                }

                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be paid by cash.'
                );
            }

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            /*
|--------------------------------------------------------------------------
| Prevent Unresolved Online Initiation
|--------------------------------------------------------------------------
*/

            $unresolvedInitiation = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->whereNotNull('initiation_token')
                ->exists();

            if ($unresolvedInitiation) {
                throw new RuntimeException(
                    'An online payment initiation is unresolved. Cash settlement is blocked.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Active Gateway Payment
            |--------------------------------------------------------------------------
            */

            $activeOnlinePayment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->where('gateway', 'zarinpal')
                ->whereNotNull('authority')
                ->lockForUpdate()
                ->exists();

            if ($activeOnlinePayment) {
                throw new RuntimeException(
                    'An online payment is in progress. Resolve it before cash settlement.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Current Remaining Amount
            |--------------------------------------------------------------------------
            */

            $remainingAmount = $this->getBookingRemainingAmount($booking);

            if ($remainingAmount <= 0) {
                throw new RuntimeException(
                    'This booking has no remaining balance.'
                );
            }

            if ($payment->type === 'remaining') {
                if (abs((float) $payment->amount - $remainingAmount) > 0.001) {
                    throw new RuntimeException(
                        'Payment amount does not match the current remaining balance.'
                    );
                }
            }
            if (
                $payment->type === 'deposit' &&
                (float) $payment->amount > $remainingAmount + 0.001
            ) {
                throw new RuntimeException(
                    'Deposit amount exceeds the current outstanding balance.'
                );
            }
            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Offline Payment
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {
                $duplicate = Payment::query()
                    ->where('offline_id', $offlineId)
                    ->where('id', '!=', $payment->id)
                    ->exists();

                if ($duplicate) {
                    throw new RuntimeException(
                        'This offline payment has already been synced.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'offline_id' => $payment->offline_id,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ];

            /*
            |--------------------------------------------------------------------------
            | Mark As Paid
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'payment_method' => 'cash',
                'status' => 'paid',
                'gateway' => null,
                'transaction_id' => null,
                'reference_number' => null,
                'authority' => null,
                'offline_id' => $offlineId,
                'paid_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction = AccountingTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'income')
                ->lockForUpdate()
                ->first();

            if (!$accountingTransaction) {
                $accountingTransaction = AccountingTransaction::create([
                    'client_id' => $payment->client_id,
                    'booking_id' => $payment->booking_id,
                    'payment_id' => $payment->id,
                    'type' => 'income',
                    'category' => 'service_payment',
                    'payment_method' => 'cash',
                    'amount' => $payment->amount,
                    'reference_number' => null,
                    'description' => $payment->type === 'deposit'
                        ? 'Service payment - deposit via cash'
                        : 'Service payment - remaining via cash',
                    'transaction_date' => $payment->paid_at ?? now(),
                    'status' => 'completed',
                    'offline_id' => $offlineId,
                    'synced_at' => $offlineId !== null ? now() : null,
                    'created_by' => $createdBy,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Cash Register
            |--------------------------------------------------------------------------
            */

            $this->syncCashRegister($accountingTransaction);

            /*
            |--------------------------------------------------------------------------
            | Recalculate Booking
            |--------------------------------------------------------------------------
            */

            $this->recalculateBookingFinancialStatus($booking);

            $payment->refresh();

            /*
            |--------------------------------------------------------------------------
            | Payment Notification
            |--------------------------------------------------------------------------
            */

            $this->createPaymentSuccessNotification($payment);

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(
                action: 'payment_paid',
                entityType: 'payment',
                entityId: $payment->id,
                bookingId: $payment->booking_id,
                clientId: $payment->client_id,
                description: $payment->type === 'deposit'
                    ? 'Deposit payment paid via cash'
                    : 'Remaining payment paid via cash',
                oldValues: $oldValues,
                newValues: [
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'offline_id' => $payment->offline_id,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'amount' => (float) $payment->amount,
                ],
                userId: $createdBy
            );

            return $payment->fresh();

        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Set ZarinPal Authority
    |--------------------------------------------------------------------------
    */

    public function setAuthority(
        Payment $payment,
        string $authority
    ): Payment {

        $authority = trim($authority);

        if ($authority === '') {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        return DB::transaction(function () use ($payment, $authority) {

            // Always lock Booking before Payment.
            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $payment->booking_id !== (int) $booking->id) {
                throw new RuntimeException(
                    'Payment does not belong to this booking.'
                );
            }

            if (!in_array($payment->type, ['deposit', 'remaining'], true)) {
                throw new RuntimeException(
                    'Invalid payment type.'
                );
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'Only pending payments can receive an authority.'
                );
            }

            // An existing authority must never be overwritten.
            if (!empty($payment->authority)) {

                if ($payment->authority === $authority
                    && $payment->gateway === 'zarinpal') {
                    return $payment->fresh();
                }

                throw new RuntimeException(
                    'This payment already has a different authority.'
                );
            }

            // Prevent duplicate authority across payments.
            $duplicate = Payment::query()
                ->where('authority', $authority)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    'This authority is already assigned to another payment.'
                );
            }

            $payment->update([
                'authority' => $authority,
                'gateway' => 'zarinpal',
                'payment_method' => 'online',
            ]);

            return $payment->fresh();

        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Recalculate Booking Financial Status
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| Recalculate Booking Financial Status
|--------------------------------------------------------------------------
*/

    public function recalculateBookingFinancialStatus(
        Booking $booking
    ): Booking {

        /*
        |--------------------------------------------------------------------------
        | Store Old Booking Status
        |--------------------------------------------------------------------------
        */

        $oldBookingStatus = $booking->status;

        /*
        |--------------------------------------------------------------------------
        | Calculate Paid Amount
        |--------------------------------------------------------------------------
        */

        $paidAmount = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->whereIn('type', ['deposit', 'remaining'])
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | Calculate Refunded Amount
        |--------------------------------------------------------------------------
        */

        $refundedAmount = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', 'refund')
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | Net Paid Amount
        |--------------------------------------------------------------------------
        */

        $netPaidAmount = max(
            0,
            (float) $paidAmount - (float) $refundedAmount
        );

        /*
        |--------------------------------------------------------------------------
        | Remaining Amount
        |--------------------------------------------------------------------------
        */

        $remainingAmount = max(
            0,
            (float) $booking->total_amount - $netPaidAmount
        );

        /*
        |--------------------------------------------------------------------------
        | Prepare Booking Update
        |--------------------------------------------------------------------------
        */

        $updateData = [
            'paid_amount' => $netPaidAmount,

            'payment_status' => $remainingAmount <= 0
                ? 'paid'
                : 'pending',
        ];

        /*
        |--------------------------------------------------------------------------
        | Automatically Confirm Fully Paid Booking
        |--------------------------------------------------------------------------
        */

        /*
|--------------------------------------------------------------------------
| Check Paid Deposit
|--------------------------------------------------------------------------
*/

        $paidDepositAmount = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', 'deposit')
            ->where('status', 'paid')
            ->sum('amount');

        $requiredDepositAmount = (float) $booking->deposit_amount;

        /*
        |--------------------------------------------------------------------------
        | Confirm Booking After Deposit Payment
        |--------------------------------------------------------------------------
        |
        | رزرو زمانی قطعی می‌شود که مبلغ بیعانه مورد نیاز پرداخت شده باشد.
        | برای Confirm شدن نیازی به تسویه کامل رزرو نیست.
        |
        */

        if (
            $requiredDepositAmount > 0 &&
            (float) $paidDepositAmount >= $requiredDepositAmount &&
            $booking->status === 'awaiting_payment'
        ) {
            $updateData['status'] = 'confirmed';
        }

        /*
        |--------------------------------------------------------------------------
        | Update Booking
        |--------------------------------------------------------------------------
        */

        $booking->update($updateData);

        $booking->refresh();

        /*
        |--------------------------------------------------------------------------
        | Booking Confirmation Notification
        |--------------------------------------------------------------------------
        |
        | فقط زمانی Notification ساخته می‌شود که وضعیت واقعاً
        | از awaiting_payment به confirmed تغییر کرده باشد.
        |
        */

        if (
            $oldBookingStatus === 'awaiting_payment' &&
            $booking->status === 'confirmed'
        ) {
            /*
            |--------------------------------------------------------------------------
            | Internal Notification
            |--------------------------------------------------------------------------
            */

            $alreadyExists = $booking->notifications()
                ->where('type', 'booking_confirmed')
                ->exists();

            if (!$alreadyExists) {
                $booking->notifications()->create([
                    'user_id' => null,
                    'client_id' => $booking->client_id,
                    'type' => 'booking_confirmed',
                    'title' => 'Booking Confirmed',
                    'message' =>
                        'Your booking has been confirmed for '
                        . $booking->booking_date->format('Y-m-d')
                        . ' at '
                        . Carbon::createFromFormat(
                            'H:i:s',
                            $booking->start_time
                        )->format('H:i')
                        . '.',
                    'is_read' => false,
                    'read_at' => null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Booking Confirmation SMS
            |--------------------------------------------------------------------------
            */

            $client = $booking->client;

            if ($client && !empty($client->phone)) {

                $smsAlreadySent = $booking->smsLogs()
                    ->where('type', 'booking_confirmed')
                    ->where('status', 'sent')
                    ->exists();

                if (!$smsAlreadySent) {
                    try {
                        app(SmsService::class)->sendBookingConfirmed(
                            phone: $client->phone,
                            date: \Morilog\Jalali\Jalalian::fromCarbon(
                                $booking->booking_date
                            )->format('Y/m/d'),
                            time: Carbon::createFromFormat(
                                'H:i:s',
                                $booking->start_time
                            )->format('H:i'),
                            booking: $booking,
                            client: $client
                        );
                    } catch (\Throwable $e) {
                        Log::error('Booking confirmation SMS failed', [
                            'booking_id' => $booking->id,
                            'client_id' => $client->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return $booking;
    }
    /*
    |--------------------------------------------------------------------------
    | Create Payment Success Notification
    |--------------------------------------------------------------------------
    */

    private function createPaymentSuccessNotification(
        Payment $payment
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Internal Notification
        |--------------------------------------------------------------------------
        */

        $alreadyExists = $payment->booking
            ->notifications()
            ->where('type', 'payment_successful')
            ->where('message', 'like', '%Payment #' . $payment->id . '%')
            ->exists();

        if (!$alreadyExists) {

            $paymentType = $payment->type === 'deposit'
                ? 'Deposit'
                : 'Remaining payment';

            $payment->booking
                ->notifications()
                ->create([
                    'user_id' => null,
                    'client_id' => $payment->client_id,
                    'type' => 'payment_successful',
                    'title' => 'Payment Successful',
                    'message' =>
                        $paymentType
                        . ' of '
                        . number_format((float) $payment->amount)
                        . ' was paid successfully. Payment #'
                        . $payment->id
                        . '.',
                    'is_read' => false,
                    'read_at' => null,
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Success SMS
        |--------------------------------------------------------------------------
        */

        $client = $payment->client;

        if (!$client || empty($client->phone)) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate SMS
        |--------------------------------------------------------------------------
        */

        $smsAlreadySent = $payment->booking
            ->smsLogs()
            ->where('type', 'payment_success')
            ->where('status', 'sent')
            ->where('message', 'Payment successful #' . $payment->id)
            ->exists();

        if ($smsAlreadySent) {
            return;
        }

        try {

            app(SmsService::class)->sendTemplate(
                phone: $client->phone,

                templateId: (int) config(
                    'services.smsir.templates.payment_success'
                ),

                parameters: [
                    'AMOUNT' => number_format(
                        (float) $payment->amount,
                        0,
                        '.',
                        ''
                    ),
                ],

                type: 'payment_success',

                booking: $payment->booking,

                client: $client,

                message: 'Payment successful #' . $payment->id
            );

        } catch (\Throwable $e) {

            Log::error('Payment success SMS failed', [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'client_id' => $payment->client_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Sync Cash Register
    |--------------------------------------------------------------------------
    */

    private function syncCashRegister(
        AccountingTransaction $accountingTransaction
    ): void {
        if ($accountingTransaction->payment_method !== 'cash') {
            return;
        }

        if ($accountingTransaction->status !== 'completed') {
            return;
        }

        if ($accountingTransaction->type === 'income') {
            app(CashRegisterService::class)->addIncome(
                $accountingTransaction
            );

            return;
        }

        if ($accountingTransaction->type === 'refund') {
            app(CashRegisterService::class)->addRefund(
                $accountingTransaction
            );
        }
    }
    public function settleRemainingByCash(
        Booking $booking,
        ?int $createdBy = null
    ): Payment {

        return DB::transaction(function () use ($booking, $createdBy) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled bookings cannot be settled.'
                );
            }

            $remaining = $this->getBookingRemainingAmount($booking);

            if ($remaining <= 0) {
                throw new RuntimeException(
                    'This booking has no remaining balance.'
                );
            }

            // هر درخواست شروع پرداخت آنلاین، حتی اگر Authority
            // هنوز دریافت نشده باشد، نیازمند بررسی است.
            $unresolvedInitiation = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->whereNotNull('initiation_token')
                ->exists();

            if ($unresolvedInitiation) {
                throw new RuntimeException(
                    'An online payment initiation requires review.'
                );
            }

            $activeOnlinePayment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->where('gateway', 'zarinpal')
                ->whereNotNull('authority')
                ->exists();

            if ($activeOnlinePayment) {
                throw new RuntimeException(
                    'An online payment is in progress.'
                );
            }

            if (!$booking->client) {
                throw new RuntimeException(
                    'Booking client not found.'
                );
            }

            $payment = $this->createRemainingPayment(
                $booking,
                $booking->client
            );

            if (abs((float) $payment->amount - $remaining) > 0.001) {
                throw new RuntimeException(
                    'Payment amount does not match remaining balance.'
                );
            }

            return $this->markAsPaidByCash(
                payment: $payment,
                createdBy: $createdBy
            );

        }, 3);
    }

    public function settleRemainingByPos(
        Booking $booking,
        int $terminalId,
        string $referenceNumber,
        ?int $createdBy = null
    ): Payment {

        $referenceNumber = trim($referenceNumber);

        if ($referenceNumber === '') {
            throw new RuntimeException(
                'POS reference number is required.'
            );
        }

        return DB::transaction(function () use (
            $booking,
            $terminalId,
            $referenceNumber,
            $createdBy
        ) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled bookings cannot be settled.'
                );
            }

            $this->ensureNoUnresolvedPosPayment($booking);

            $remaining = $this->getBookingRemainingAmount($booking);

            if ($remaining <= 0) {
                throw new RuntimeException(
                    'This booking has no remaining balance.'
                );
            }

            if (!$booking->client) {
                throw new RuntimeException(
                    'Booking client not found.'
                );
            }

            // از منطق موجود برای ایجاد یا بازیابی پرداخت استفاده می‌کنیم.
            $payment = $this->createRemainingPayment(
                $booking,
                $booking->client
            );

            if (abs((float) $payment->amount - $remaining) > 0.001) {
                throw new RuntimeException(
                    'Payment amount does not match remaining balance.'
                );
            }

            return app(\App\Services\PosPaymentService::class)
                ->markAsPaidManually(
                    payment: $payment,
                    terminalId: $terminalId,
                    referenceNumber: $referenceNumber,
                    createdBy: $createdBy
                );

        }, 3);
    }

    public function reserveRemainingPaymentInitiation(
        Booking $booking,
                $client
    ): array {

        return DB::transaction(function () use ($booking, $client) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$client ||
                (int) $booking->client_id !== (int) $client->id
            ) {
                throw new RuntimeException(
                    'Booking does not belong to this client.'
                );
            }

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            $remaining = $this->getBookingRemainingAmount($booking);

            if ($remaining <= 0) {
                throw new RuntimeException(
                    'This booking has no remaining balance.'
                );
            }

            $pendingPayments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'remaining')
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($pendingPayments->count() > 1) {
                throw new RuntimeException(
                    'Multiple pending payments require review.'
                );
            }

            $payment = $pendingPayments->first();

            if ($payment) {

                // An existing initiation must be resolved first.
                // Expiration alone does not prove gateway failure.
                if (!empty($payment->initiation_token)) {
                    throw new RuntimeException(
                        'Payment initiation is already in progress or requires review.'
                    );
                }

                if (
                    !empty($payment->authority) ||
                    !empty($payment->transaction_id) ||
                    !empty($payment->reference_number) ||
                    !empty($payment->offline_id)
                ) {
                    throw new RuntimeException(
                        'Existing payment requires review.'
                    );
                }

            } else {

                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'client_id' => $client->id,
                    'amount' => $remaining,
                    'type' => 'remaining',
                    'payment_method' => 'online',
                    'status' => 'pending',
                    'gateway' => null,
                    'authority' => null,
                ]);
            }

            $token = (string) \Illuminate\Support\Str::uuid();

            $payment->update([
                'client_id' => $client->id,
                'amount' => $remaining,
                'payment_method' => 'online',
                'initiation_token' => $token,
                'initiation_expires_at' => now()->addMinutes(5),
            ]);

            return [
                'payment' => $payment->fresh(),
                'token' => $token,
            ];

        }, 3);
    }
    public function completeRemainingPaymentInitiation(
        Payment $payment,
        string $token,
        string $authority
    ): Payment {

        $authority = trim($authority);

        if ($authority === '') {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        return DB::transaction(function () use (
            $payment,
            $token,
            $authority
        ) {

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $payment->type !== 'remaining' ||
                $payment->status !== 'pending'
            ) {
                throw new RuntimeException(
                    'Payment is not eligible for initiation completion.'
                );
            }

            if (
                !hash_equals(
                    (string) $payment->initiation_token,
                    $token
                )
            ) {
                throw new RuntimeException(
                    'Payment initiation token mismatch.'
                );
            }

            if (!empty($payment->authority)) {
                throw new RuntimeException(
                    'Payment already has an authority.'
                );
            }

            $duplicate = Payment::query()
                ->where('authority', $authority)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    'Authority is already assigned.'
                );
            }

            $payment->update([
                'gateway' => 'zarinpal',
                'payment_method' => 'online',
                'authority' => $authority,
                'initiation_token' => null,
                'initiation_expires_at' => null,
            ]);

            return $payment->fresh();

        }, 3);
    }
    public function releaseRemainingPaymentInitiation(
        Payment $payment,
        string $token
    ): Payment {

        return DB::transaction(function () use ($payment, $token) {

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureNoUnresolvedPosPayment($booking);

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $payment->status !== 'pending' ||
                $payment->type !== 'remaining'
            ) {
                throw new RuntimeException(
                    'Payment cannot be released.'
                );
            }

            if (
                !hash_equals(
                    (string) $payment->initiation_token,
                    $token
                )
            ) {
                throw new RuntimeException(
                    'Payment initiation token mismatch.'
                );
            }

            if (!empty($payment->authority)) {
                throw new RuntimeException(
                    'Payment has an authority and cannot be released.'
                );
            }

            $payment->update([
                'initiation_token' => null,
                'initiation_expires_at' => null,
            ]);

            return $payment->fresh();

        }, 3);
    }
    public function reserveDepositPaymentInitiation(
        Booking $booking,
                $client
    ): array {

        return DB::transaction(function () use ($booking, $client) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureNoUnresolvedPosPayment($booking);

            if (
                !$client ||
                (int) $booking->client_id !== (int) $client->id
            ) {
                throw new RuntimeException(
                    'Booking does not belong to this client.'
                );
            }

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            // All deposit payments must be examined before reuse.
            $depositPayments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'deposit')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($depositPayments->contains(
                fn (Payment $payment) => $payment->status === 'paid'
            )) {
                throw new RuntimeException(
                    'Booking deposit has already been paid.'
                );
            }

            $pendingPayments = $depositPayments
                ->where('status', 'pending');

            if ($pendingPayments->count() > 1) {
                throw new RuntimeException(
                    'Multiple pending deposit payments require review.'
                );
            }

            $existing = $pendingPayments->first();

            if ($existing) {
                if (!empty($existing->initiation_token)) {
                    throw new RuntimeException(
                        'Deposit payment initiation is unresolved.'
                    );
                }

                if (
                    !empty($existing->authority) ||
                    !empty($existing->transaction_id) ||
                    !empty($existing->reference_number) ||
                    !empty($existing->offline_id)
                ) {
                    throw new RuntimeException(
                        'Existing deposit payment requires review.'
                    );
                }
            }

            // Reuse existing business rules for deposit amount.
            $payment = $this->createDepositPayment(
                $booking,
                $client
            );

            if (
                $payment->status !== 'pending' ||
                $payment->type !== 'deposit'
            ) {
                throw new RuntimeException(
                    'Deposit payment is not eligible.'
                );
            }

            // Refresh the locked payment record.
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !empty($payment->initiation_token) ||
                !empty($payment->authority) ||
                !empty($payment->transaction_id) ||
                !empty($payment->reference_number) ||
                !empty($payment->offline_id)
            ) {
                throw new RuntimeException(
                    'Deposit payment requires review.'
                );
            }

            $token = (string) \Illuminate\Support\Str::uuid();

            $payment->forceFill([
                'initiation_token' => $token,
                'initiation_expires_at' => now()->addMinutes(5),
                'payment_method' => 'online',
            ])->save();

            return [
                'payment' => $payment->fresh(),
                'token' => $token,
            ];

        }, 3);
    }
    public function completeDepositPaymentInitiation(
        Payment $payment,
        string $token,
        string $authority
    ): Payment {

        $authority = trim($authority);

        if ($authority === '') {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        return DB::transaction(function () use (
            $payment,
            $token,
            $authority
        ) {

            $booking = Booking::query()
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status === 'cancelled') {
                throw new RuntimeException(
                    'Cancelled booking cannot be paid.'
                );
            }

            if (
                $payment->type !== 'deposit' ||
                $payment->status !== 'pending'
            ) {
                throw new RuntimeException(
                    'Deposit payment is not pending.'
                );
            }

            if (
                empty($payment->initiation_token) ||
                !hash_equals(
                    (string) $payment->initiation_token,
                    $token
                )
            ) {
                throw new RuntimeException(
                    'Payment initiation token mismatch.'
                );
            }

            if (!empty($payment->authority)) {
                throw new RuntimeException(
                    'Payment already has an authority.'
                );
            }

            $duplicate = Payment::query()
                ->where('authority', $authority)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    'Authority is already assigned.'
                );
            }

            $payment->forceFill([
                'gateway' => 'zarinpal',
                'payment_method' => 'online',
                'authority' => $authority,
                'initiation_token' => null,
                'initiation_expires_at' => null,
            ])->save();

            return $payment->fresh();

        }, 3);
    }
    public function ensureNoUnresolvedPosPayment(
        \App\Models\Booking $booking
    ): void {
        $hasUnresolvedPos = \App\Models\PosPaymentRequest::query()
            ->whereHas('payment', function ($query) use ($booking) {
                $query->where('booking_id', $booking->id);
            })
            ->whereIn('status', [
                'pending',
                'processing',
                'unknown',
            ])
            ->exists();

        if ($hasUnresolvedPos) {
            throw new \RuntimeException(
                'An unresolved POS transaction exists. Reconciliation is required.'
            );
        }
    }
}
