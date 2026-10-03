<?php


namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function createDepositPayment(
        Booking $booking,
                $client
    ): Payment
    {
        if ((int)$booking->client_id !== (int)$client->id) {
            throw new RuntimeException('Booking does not belong to this client.');
        }

        if ($booking->status === 'cancelled') {
            throw new RuntimeException('Cancelled booking cannot be paid.');
        }

        if ($booking->status === 'completed') {
            throw new RuntimeException('Completed booking cannot be paid.');
        }

        if ((float)$booking->deposit_amount <= 0) {
            throw new RuntimeException('This booking does not require a deposit.');
        }

        if ($booking->payment_status === 'paid') {
            throw new RuntimeException('Booking deposit has already been paid.');
        }

        return DB::transaction(function () use ($booking, $client) {

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
                'status' => 'pending',
                'gateway' => null,
                'transaction_id' => null,
                'paid_at' => null,
            ]);
        });
    }
    public function markDepositAsPaid(
        Payment $payment,
        string $gateway,
        string $transactionId,
        float $verifiedAmount
    ): Payment {
        if ($payment->type !== 'deposit') {
            throw new RuntimeException(
                'This payment is not a deposit payment.'
            );
        }

        if ($payment->status === 'paid') {
            return $payment->fresh();
        }

        if ($payment->status !== 'pending') {
            throw new RuntimeException(
                'This payment cannot be marked as paid.'
            );
        }

        if (empty(trim($gateway))) {
            throw new RuntimeException(
                'Payment gateway is required.'
            );
        }

        if (empty(trim($transactionId))) {
            throw new RuntimeException(
                'Transaction ID is required.'
            );
        }

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

        return DB::transaction(function () use (
            $payment,
            $gateway,
            $transactionId,
            $verifiedAmount
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

            if ($payment->status === 'paid') {
                return $payment;
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'This payment cannot be marked as paid.'
                );
            }

            if ((float) $payment->amount !== $verifiedAmount) {
                throw new RuntimeException(
                    'Verified payment amount does not match the payment amount.'
                );
            }

            $payment->update([
                'status' => 'paid',
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
            ]);

            $booking = $payment->booking()
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new RuntimeException(
                    'Booking not found.'
                );
            }

            $depositPayments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'deposit')
                ->where('status', 'paid')
                ->sum('amount');

            $booking->update([
                'paid_amount' => $depositPayments,
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);

            return $payment->fresh();
        });
    }
    public function setAuthority(
        Payment $payment,
        string $authority
    ): Payment {
        if ($payment->type !== 'deposit') {
            throw new RuntimeException(
                'This payment is not a deposit payment.'
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

        return DB::transaction(function () use ($payment, $authority) {

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
}
