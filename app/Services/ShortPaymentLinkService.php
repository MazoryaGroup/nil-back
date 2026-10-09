<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Str;
use RuntimeException;

class ShortPaymentLinkService
{
    public function getOrCreate(Payment $payment): string
    {
        $payment = $payment->fresh();

        if (
            !$payment ||
            $payment->status !== 'pending' ||
            $payment->payment_method !== 'online' ||
            $payment->gateway !== 'zarinpal' ||
            empty($payment->authority) ||
            !empty($payment->initiation_token)
        ) {
            throw new RuntimeException(
                'Payment is not ready for a short link.'
            );
        }

        if (!$payment->short_link_token) {
            $token = Str::random(16);

            // Atomic assignment: prevents overwriting
            // a token created by another request.
            Payment::query()
                ->whereKey($payment->id)
                ->whereNull('short_link_token')
                ->update([
                    'short_link_token' => $token,
                ]);

            $payment->refresh();
        }

        $baseUrl = rtrim(
            (string) config('services.nil.frontend_url'),
            '/'
        );

        if ($baseUrl === '') {
            throw new RuntimeException(
                'NIL frontend URL is not configured.'
            );
        }

        return $baseUrl . '/p/' . $payment->short_link_token;
    }
}
