<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReceiptService
{
    public function createReceipt(
        Payment $payment,
        ?int $createdBy = null
    ): Receipt {

        if ($payment->status !== 'paid') {
            throw new RuntimeException(
                'Only paid payments can have a receipt.'
            );
        }

        return DB::transaction(function () use (
            $payment,
            $createdBy
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

            if ($payment->status !== 'paid') {
                throw new RuntimeException(
                    'Only paid payments can have a receipt.'
                );
            }

            $existingReceipt = Receipt::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($existingReceipt) {
                return $existingReceipt;
            }

            $receiptNumber = $this->generateReceiptNumber();

            return Receipt::create([
                'payment_id' => $payment->id,
                'client_id' => $payment->client_id,
                'booking_id' => $payment->booking_id,
                'receipt_number' => $receiptNumber,
                'amount' => $payment->amount,
                'issued_at' => now(),
                'created_by' => $createdBy,
            ]);
        });
    }

    protected function generateReceiptNumber(): string
    {
        do {
            $number = 'NIL-'
                . now()->format('Ymd')
                . '-'
                . str_pad(
                    (string) random_int(1, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

        } while (
            Receipt::query()
                ->where('receipt_number', $number)
                ->exists()
        );

        return $number;
    }
}
