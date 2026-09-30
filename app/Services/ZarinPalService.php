<?php


namespace App\Services;

use RuntimeException;

class ZarinPalService
{
    /**
     * Create a payment request with ZarinPal.
     *
     * This method will be connected to the real ZarinPal SDK
     * once the merchant ID is available.
     */
    public function requestPayment(
        int|float $amount,
        string    $callbackUrl,
        string    $description,
        ?string   $email = null,
        ?string   $mobile = null
    ): array
    {
        $merchantId = config('services.zarinpal.merchant_id');

        if (empty($merchantId)) {
            throw new RuntimeException(
                'ZarinPal merchant ID is not configured.'
            );
        }

        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        if (empty(trim($callbackUrl))) {
            throw new RuntimeException(
                'ZarinPal callback URL is required.'
            );
        }

        if (empty(trim($description))) {
            throw new RuntimeException(
                'Payment description is required.'
            );
        }

        /*
         * Real ZarinPal request will be implemented here.
         *
         * Expected flow:
         *
         * 1. Send payment request to ZarinPal.
         * 2. Receive Authority.
         * 3. Store Authority in payments.transaction_id
         *    or a dedicated authority field.
         * 4. Return payment URL to frontend.
         */

        throw new RuntimeException(
            'ZarinPal payment request is not connected yet.'
        );
    }

    /**
     * Build ZarinPal payment redirect URL.
     */
    public function getPaymentUrl(string $authority): string
    {
        if (empty(trim($authority))) {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        $sandbox = (bool)config('services.zarinpal.sandbox', true);

        $baseUrl = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://www.zarinpal.com/pg/StartPay/';

        return $baseUrl . trim($authority);
    }
}
