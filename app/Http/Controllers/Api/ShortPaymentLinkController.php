<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\ZarinPalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShortPaymentLinkController extends Controller
{
    public function resolve(
        string $token,
        ZarinPalService $zarinpal,
        PaymentService $paymentService
    ): RedirectResponse|Response {

        if (!preg_match('/^[A-Za-z0-9]{16}$/D', $token)) {
            abort(404, 'Payment link is invalid.');
        }

        $payment = Payment::with('booking')
            ->where('short_link_token', $token)
            ->first();

        if (
            !$payment ||
            $payment->status !== 'pending' ||
            $payment->payment_method !== 'online' ||
            $payment->gateway !== 'zarinpal' ||
            empty($payment->authority) ||
            !empty($payment->initiation_token)
        ) {
            abort(404, 'Payment link is unavailable.');
        }

        $booking = $payment->booking;

        if (
            !$booking ||
            $booking->status === 'cancelled' ||
            (int) $payment->client_id !== (int) $booking->client_id
        ) {
            abort(404, 'Payment link is unavailable.');
        }

        if ($payment->type === 'remaining') {
            $remaining = (float) $paymentService
                ->getBookingRemainingAmount($booking->fresh());

            if (
                $remaining <= 0 ||
                abs((float) $payment->amount - $remaining) > 0.001
            ) {
                abort(404, 'Payment amount is no longer valid.');
            }
        } elseif ($payment->type !== 'deposit') {
            abort(404, 'Payment type is invalid.');
        }

        try {
            $paymentUrl = $zarinpal->getPaymentUrl(
                (string) $payment->authority
            );

            $parts = parse_url($paymentUrl);

            if (
                !is_array($parts) ||
                ($parts['scheme'] ?? '') !== 'https' ||
                !in_array(
                    strtolower($parts['host'] ?? ''),
                    [
                        'payment.zarinpal.com',
                        'www.zarinpal.com',
                        'sandbox.zarinpal.com',
                    ],
                    true
                )
            ) {
                throw new \RuntimeException(
                    'Invalid gateway redirect URL.'
                );
            }

            return redirect()->away($paymentUrl, 302);

        } catch (Throwable $e) {

            Log::error('Short payment redirect failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            abort(503, 'Payment gateway is unavailable.');
        }
    }
}
