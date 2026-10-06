<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;

class CustomerFinancialService
{
    /**
     * Return complete financial summary for a customer.
     */
    public function getFinancialSummary(Client $client): array
    {
        $bookings = Booking::query()
            ->where('client_id', $client->id)
            ->get();

        $totalServicesAmount = (float) $bookings->sum('total_amount');

        $payments = Payment::query()
            ->where('client_id', $client->id)
            ->where('status', 'paid')
            ->whereIn('type', ['deposit', 'remaining'])
            ->get();

        $refunds = Payment::query()
            ->where('client_id', $client->id)
            ->where('type', 'refund')
            ->whereIn('status', ['paid', 'refunded'])
            ->get();

        $totalDepositAmount = (float) $payments
            ->where('type', 'deposit')
            ->sum('amount');

        $totalRemainingAmount = (float) $payments
            ->where('type', 'remaining')
            ->sum('amount');

        $totalPaidBeforeRefund = (float) $payments->sum('amount');

        $totalRefundAmount = (float) $refunds->sum('amount');

        $totalPaidAmount = max(
            0,
            $totalPaidBeforeRefund - $totalRefundAmount
        );

        $outstandingAmount = max(
            0,
            $totalServicesAmount - $totalPaidAmount
        );

        return [
            'client_id' => $client->id,

            'total_services_amount' => $totalServicesAmount,

            'total_deposit_amount' => $totalDepositAmount,

            'total_remaining_amount' => $totalRemainingAmount,

            'total_paid_before_refund' => $totalPaidBeforeRefund,

            'total_refund_amount' => $totalRefundAmount,

            'total_paid_amount' => $totalPaidAmount,

            'outstanding_amount' => $outstandingAmount,
        ];
    }
}
