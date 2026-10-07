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

        return DB::transaction(function () use (
            $booking,
            $client
        ) {
            /*
            |--------------------------------------------------------------------------
            | Lock Booking
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate Current Remaining Amount
            |--------------------------------------------------------------------------
            */

            $remainingAmount =
                $this->getBookingRemainingAmount($booking);

            if ($remainingAmount <= 0) {
                throw new RuntimeException(
                    'This booking has no remaining balance.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Find Existing Pending Remaining Payment
            |--------------------------------------------------------------------------
            |
            | فقط Payment در انتظار پرداخت قابل استفاده مجدد است.
            | Payment پرداخت‌شده نباید برگردانده شود چون ممکن است
            | بعداً Refund انجام شده و دوباره مانده ایجاد شده باشد.
            |
            */

            $existingPayment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'remaining')
                ->where('status', 'pending')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Update Existing Pending Payment
            |--------------------------------------------------------------------------
            |
            | اگر مانده رزرو از زمان ساخت Payment تغییر کرده باشد،
            | مبلغ Pending Payment با مانده واقعی فعلی Sync می‌شود.
            |
            */

            if ($existingPayment) {
                $existingPayment->update([
                    'client_id' => $client->id,
                    'amount' => $remainingAmount,
                    'payment_method' => 'online',
                    'gateway' => null,
                    'transaction_id' => null,
                    'reference_number' => null,
                    'authority' => null,
                    'offline_id' => null,
                    'paid_at' => null,
                ]);

                return $existingPayment->fresh();
            }

            /*
            |--------------------------------------------------------------------------
            | Create New Remaining Payment
            |--------------------------------------------------------------------------
            */

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
        });
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

        if ((float) $booking->deposit_amount <= 0) {
            throw new RuntimeException(
                'This booking does not require a deposit.'
            );
        }

        if ($booking->payment_status === 'paid') {
            throw new RuntimeException(
                'Booking deposit has already been paid.'
            );
        }

        return DB::transaction(function () use (
            $booking,
            $client
        ) {
            $existingPayment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'deposit')
                ->whereIn('status', ['pending', 'paid'])
                ->latest('id')
                ->first();

            if ($existingPayment) {
                return $existingPayment;
            }

            return Payment::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'amount' => $booking->deposit_amount,
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
        });
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

        /*
        |--------------------------------------------------------------------------
        | Validate Payment Type
        |--------------------------------------------------------------------------
        */

        if ($payment->type !== 'deposit') {
            throw new RuntimeException(
                'This payment is not a deposit payment.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return $payment->fresh();
        }

        if ($payment->status !== 'pending') {
            throw new RuntimeException(
                'This payment cannot be marked as paid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Gateway
        |--------------------------------------------------------------------------
        */

        $gateway = trim($gateway);

        if ($gateway === '') {
            throw new RuntimeException(
                'Payment gateway is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Transaction ID
        |--------------------------------------------------------------------------
        */

        $transactionId = trim($transactionId);

        if ($transactionId === '') {
            throw new RuntimeException(
                'Transaction ID is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if ($verifiedAmount <= 0) {
            throw new RuntimeException(
                'Verified payment amount must be greater than zero.'
            );
        }

        if ((float) $payment->amount !== $verifiedAmount) {
            throw new RuntimeException(
                'Verified payment amount does not match the payment amount.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $payment,
            $gateway,
            $transactionId,
            $verifiedAmount,
            $createdBy
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException(
                    'Payment not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Status
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {
                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be marked as paid.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Payment Type
            |--------------------------------------------------------------------------
            */

            if ($payment->type !== 'deposit') {
                throw new RuntimeException(
                    'This payment is not a deposit payment.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Amount
            |--------------------------------------------------------------------------
            */

            if ((float) $payment->amount !== $verifiedAmount) {
                throw new RuntimeException(
                    'Verified payment amount does not match the payment amount.'
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

                    'payment_method' => $payment->payment_method,

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
            | Sync Cash Register
            |--------------------------------------------------------------------------
            |
            | اگر روش پرداخت cash باشد وارد صندوق می‌شود.
            | پرداخت آنلاین روی صندوق نقدی اثری ندارد.
            |
            */

            $this->syncCashRegister(
                $accountingTransaction
            );

            /*
            |--------------------------------------------------------------------------
            | Get & Lock Booking
            |--------------------------------------------------------------------------
            */

            $booking = $payment->booking()
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate Booking Financial Status
            |--------------------------------------------------------------------------
            */

            $this->recalculateBookingFinancialStatus(
                $booking
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh Payment
            |--------------------------------------------------------------------------
            */

            $payment->refresh();
            /*
|--------------------------------------------------------------------------
| Payment Success Notification
|--------------------------------------------------------------------------
*/

            $this->createPaymentSuccessNotification(
                $payment
            );

            /*
            |--------------------------------------------------------------------------
            | Create Audit Log
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

            /*
            |--------------------------------------------------------------------------
            | Return Payment
            |--------------------------------------------------------------------------
            */

            return $payment->fresh();
        });
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

        /*
        |--------------------------------------------------------------------------
        | Validate Payment Type
        |--------------------------------------------------------------------------
        */

        if ($payment->type !== 'remaining') {
            throw new RuntimeException(
                'This payment is not a remaining payment.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return $payment->fresh();
        }

        if ($payment->status !== 'pending') {
            throw new RuntimeException(
                'This payment cannot be marked as paid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Gateway
        |--------------------------------------------------------------------------
        */

        $gateway = trim($gateway);

        if ($gateway === '') {
            throw new RuntimeException(
                'Payment gateway is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Transaction ID
        |--------------------------------------------------------------------------
        */

        $transactionId = trim($transactionId);

        if ($transactionId === '') {
            throw new RuntimeException(
                'Transaction ID is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if ($verifiedAmount <= 0) {
            throw new RuntimeException(
                'Verified payment amount must be greater than zero.'
            );
        }

        if ((float) $payment->amount !== $verifiedAmount) {
            throw new RuntimeException(
                'Verified payment amount does not match the payment amount.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $payment,
            $gateway,
            $transactionId,
            $verifiedAmount,
            $createdBy
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException(
                    'Payment not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Payment Type
            |--------------------------------------------------------------------------
            */

            if ($payment->type !== 'remaining') {
                throw new RuntimeException(
                    'This payment is not a remaining payment.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Status
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {
                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be marked as paid.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Amount
            |--------------------------------------------------------------------------
            */

            if ((float) $payment->amount !== $verifiedAmount) {
                throw new RuntimeException(
                    'Verified payment amount does not match the payment amount.'
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

                    'payment_method' => $payment->payment_method,

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

            /*
            |--------------------------------------------------------------------------
            | Sync Cash Register
            |--------------------------------------------------------------------------
            */

            $this->syncCashRegister(
                $accountingTransaction
            );

            /*
            |--------------------------------------------------------------------------
            | Get & Lock Booking
            |--------------------------------------------------------------------------
            */

            $booking = $payment->booking()
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate Booking Financial Status
            |--------------------------------------------------------------------------
            */

            $this->recalculateBookingFinancialStatus(
                $booking
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh Payment
            |--------------------------------------------------------------------------
            */

            $payment->refresh();

            /*
|--------------------------------------------------------------------------
| Payment Success Notification
|--------------------------------------------------------------------------
*/

            $this->createPaymentSuccessNotification(
                $payment
            );
            /*
            |--------------------------------------------------------------------------
            | Create Audit Log
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Return Payment
            |--------------------------------------------------------------------------
            */

            return $payment->fresh();
        });
    }
    /*
 |--------------------------------------------------------------------------
 | Mark Payment As Paid By POS
 |--------------------------------------------------------------------------
 */

    public function markAsPaidByPos(
        Payment $payment,
        string $referenceNumber,
        ?int $createdBy = null,
        ?string $offlineId = null
    ): Payment {

        /*
        |--------------------------------------------------------------------------
        | Validate Payment Type
        |--------------------------------------------------------------------------
        */

        if (!in_array(
            $payment->type,
            ['deposit', 'remaining'],
            true
        )) {
            throw new RuntimeException(
                'Only deposit or remaining payments can be paid by POS.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return $payment->fresh();
        }

        if ($payment->status !== 'pending') {
            throw new RuntimeException(
                'This payment cannot be paid by POS.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Reference Number
        |--------------------------------------------------------------------------
        */

        $referenceNumber = trim($referenceNumber);

        if ($referenceNumber === '') {
            throw new RuntimeException(
                'POS reference number is required.'
            );
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
        | Transaction
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $payment,
            $referenceNumber,
            $createdBy,
            $offlineId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException(
                    'Payment not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Payment Type
            |--------------------------------------------------------------------------
            */

            if (!in_array(
                $payment->type,
                ['deposit', 'remaining'],
                true
            )) {
                throw new RuntimeException(
                    'Only deposit or remaining payments can be paid by POS.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Already Paid
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {

                /*
                 * اگر همان عملیات آفلاین قبلاً روی همین Payment
                 * ثبت شده باشد، همان Payment برگردانده می‌شود.
                 */

                if (
                    $offlineId !== null &&
                    $payment->offline_id === $offlineId
                ) {
                    return $payment->fresh();
                }

                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be paid by POS.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Offline Payment
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {

                $duplicateOfflinePayment = Payment::query()
                    ->where('offline_id', $offlineId)
                    ->where('id', '!=', $payment->id)
                    ->first();

                if ($duplicateOfflinePayment) {
                    throw new RuntimeException(
                        'This offline payment has already been synced.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate POS Reference
            |--------------------------------------------------------------------------
            */

            $duplicateReference = Payment::query()
                ->where('payment_method', 'pos')
                ->where('reference_number', $referenceNumber)
                ->where('status', 'paid')
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($duplicateReference) {
                throw new RuntimeException(
                    'This POS reference number has already been used.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values For Audit
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'status' => $payment->status,

                'payment_method' =>
                    $payment->payment_method,

                'reference_number' =>
                    $payment->reference_number,

                'offline_id' =>
                    $payment->offline_id,

                'paid_at' =>
                    $payment->paid_at?->toDateTimeString(),
            ];

            /*
            |--------------------------------------------------------------------------
            | Mark Payment As Paid
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'payment_method' => 'pos',

                'status' => 'paid',

                /*
                 * کارتخوان Gateway آنلاین ندارد.
                 */
                'gateway' => null,

                /*
                 * Transaction ID مربوط به Gateway آنلاین است.
                 */
                'transaction_id' => null,

                /*
                 * شماره پیگیری کارتخوان
                 */
                'reference_number' => $referenceNumber,

                'authority' => null,

                /*
                 * اگر عملیات از PWA آفلاین آمده باشد
                 * UUID آن اینجا ذخیره می‌شود.
                 */
                'offline_id' => $offlineId,

                'paid_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create / Find Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction =
                AccountingTransaction::query()
                    ->where(
                        'payment_id',
                        $payment->id
                    )
                    ->where(
                        'type',
                        'income'
                    )
                    ->lockForUpdate()
                    ->first();

            if (!$accountingTransaction) {

                $description =
                    $payment->type === 'deposit'
                        ? 'Service payment - deposit via POS'
                        : 'Service payment - remaining via POS';

                $accountingTransaction =
                    AccountingTransaction::create([

                        'client_id' =>
                            $payment->client_id,

                        'booking_id' =>
                            $payment->booking_id,

                        'payment_id' =>
                            $payment->id,

                        'type' =>
                            'income',

                        'category' =>
                            'service_payment',

                        'payment_method' =>
                            'pos',

                        'amount' =>
                            $payment->amount,

                        'reference_number' =>
                            $referenceNumber,

                        'description' =>
                            $description,

                        'transaction_date' =>
                            $payment->paid_at ?? now(),

                        'status' =>
                            'completed',

                        /*
                         * اتصال تراکنش حسابداری
                         * به عملیات Offline PWA
                         */
                        'offline_id' =>
                            $offlineId,

                        /*
                         * اگر offline_id داریم یعنی
                         * عملیات آفلاین به سرور Sync شده.
                         */
                        'synced_at' =>
                            $offlineId !== null
                                ? now()
                                : null,

                        'created_by' =>
                            $createdBy,
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            | POS does NOT enter Cash Register
            |--------------------------------------------------------------------------
            |
            | پرداخت POS موجودی نقدی صندوق را تغییر نمی‌دهد.
            |
            */

            /*
            |--------------------------------------------------------------------------
            | Get Booking
            |--------------------------------------------------------------------------
            */

            $booking = $payment->booking()
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate Booking Financial Status
            |--------------------------------------------------------------------------
            */

            $this->recalculateBookingFinancialStatus(
                $booking
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh Payment
            |--------------------------------------------------------------------------
            */

            $payment->refresh();
            /*
|--------------------------------------------------------------------------
| Payment Success Notification
|--------------------------------------------------------------------------
*/

            $this->createPaymentSuccessNotification(
                $payment
            );

            /*
            |--------------------------------------------------------------------------
            | Create Audit Log
            |--------------------------------------------------------------------------
            */

            app(AccountingAuditService::class)->log(

                action: 'payment_paid',

                entityType: 'payment',

                entityId: $payment->id,

                bookingId: $payment->booking_id,

                clientId: $payment->client_id,

                description:
                $payment->type === 'deposit'
                    ? 'Deposit payment paid via POS'
                    : 'Remaining payment paid via POS',

                oldValues: $oldValues,

                newValues: [

                    'status' =>
                        $payment->status,

                    'payment_method' =>
                        $payment->payment_method,

                    'reference_number' =>
                        $payment->reference_number,

                    'offline_id' =>
                        $payment->offline_id,

                    'paid_at' =>
                        $payment->paid_at
                            ?->toDateTimeString(),

                    'amount' =>
                        (float) $payment->amount,
                ],

                userId: $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return Payment
            |--------------------------------------------------------------------------
            */

            return $payment->fresh();
        });
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

        /*
        |--------------------------------------------------------------------------
        | Validate Payment Type
        |--------------------------------------------------------------------------
        */

        if (!in_array(
            $payment->type,
            ['deposit', 'remaining'],
            true
        )) {
            throw new RuntimeException(
                'Only deposit or remaining payments can be paid by cash.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return $payment->fresh();
        }

        if ($payment->status !== 'pending') {
            throw new RuntimeException(
                'This payment cannot be paid by cash.'
            );
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
        | Transaction
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $payment,
            $createdBy,
            $offlineId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException(
                    'Payment not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recheck Payment Type
            |--------------------------------------------------------------------------
            */

            if (!in_array(
                $payment->type,
                ['deposit', 'remaining'],
                true
            )) {
                throw new RuntimeException(
                    'Only deposit or remaining payments can be paid by cash.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Already Paid
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {

                if (
                    $offlineId !== null &&
                    $payment->offline_id === $offlineId
                ) {
                    return $payment->fresh();
                }

                return $payment->fresh();
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be paid by cash.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Offline Payment
            |--------------------------------------------------------------------------
            */

            if ($offlineId !== null) {

                $duplicateOfflinePayment = Payment::query()
                    ->where('offline_id', $offlineId)
                    ->where('id', '!=', $payment->id)
                    ->first();

                if ($duplicateOfflinePayment) {
                    throw new RuntimeException(
                        'This offline payment has already been synced.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Store Old Values For Audit
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
            | Mark Payment As Paid
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
            | Create / Find Accounting Transaction
            |--------------------------------------------------------------------------
            */

            $accountingTransaction = AccountingTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'income')
                ->lockForUpdate()
                ->first();

            if (!$accountingTransaction) {

                $description = $payment->type === 'deposit'
                    ? 'Service payment - deposit via cash'
                    : 'Service payment - remaining via cash';

                $accountingTransaction = AccountingTransaction::create([

                    'client_id' => $payment->client_id,

                    'booking_id' => $payment->booking_id,

                    'payment_id' => $payment->id,

                    'type' => 'income',

                    'category' => 'service_payment',

                    'payment_method' => 'cash',

                    'amount' => $payment->amount,

                    'reference_number' => null,

                    'description' => $description,

                    'transaction_date' =>
                        $payment->paid_at ?? now(),

                    'status' => 'completed',

                    'offline_id' => $offlineId,

                    'synced_at' => $offlineId !== null
                        ? now()
                        : null,

                    'created_by' => $createdBy,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Sync Cash Register
            |--------------------------------------------------------------------------
            |
            | برخلاف POS، پرداخت نقدی باید وارد صندوق شود.
            |
            */

            $this->syncCashRegister(
                $accountingTransaction
            );

            /*
            |--------------------------------------------------------------------------
            | Get Booking
            |--------------------------------------------------------------------------
            */

            $booking = $payment->booking()
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate Booking Financial Status
            |--------------------------------------------------------------------------
            */

            $this->recalculateBookingFinancialStatus(
                $booking
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh Payment
            |--------------------------------------------------------------------------
            */

            $payment->refresh();
            /*
            |--------------------------------------------------------------------------
            | Payment Success Notification
            |--------------------------------------------------------------------------
            */

            $this->createPaymentSuccessNotification(
                $payment
            );
            /*
            |--------------------------------------------------------------------------
            | Create Audit Log
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

                    'payment_method' =>
                        $payment->payment_method,

                    'offline_id' =>
                        $payment->offline_id,

                    'paid_at' =>
                        $payment->paid_at?->toDateTimeString(),

                    'amount' =>
                        (float) $payment->amount,
                ],

                userId: $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return Payment
            |--------------------------------------------------------------------------
            */

            return $payment->fresh();
        });
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
        if (!in_array($payment->type, ['deposit', 'remaining'], true)) {
            throw new RuntimeException(
                'Only deposit or remaining payments can receive an authority.'
            );
        }


        if ($payment->status !== 'pending') {
            throw new RuntimeException(
                'Only pending payments can receive an authority.'
            );
        }

        if (empty(trim($authority))) {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        return DB::transaction(function () use (
            $payment,
            $authority
        ) {
            $payment = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException(
                    'Payment not found.'
                );
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'Only pending payments can receive an authority.'
                );
            }

            $payment->update([
                'authority' => trim($authority),
                'gateway' => 'zarinpal',
            ]);

            return $payment->fresh();
        });
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
}
