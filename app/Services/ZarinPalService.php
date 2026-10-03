<?php

namespace App\Services;

use RuntimeException;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\RequestRequest;
use ZarinPal\Sdk\ZarinPal;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;

class ZarinPalService
{
    /**
     * Create a payment request with ZarinPal.
     */
    public function requestPayment(
        int|float $amount,
        string $callbackUrl,
        string $description,
        ?string $email = null,
        ?string $mobile = null
    ): array {
        $merchantId = config('services.zarinpal.merchant_id');

        if (empty($merchantId) || $merchantId === 'YOUR_MERCHANT_ID') {
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

        $options = new \ZarinPal\Sdk\Options();

        $zarinpal = new ZarinPal($options);

        $request = new RequestRequest();

        $request->amount = (int) $amount;
        $request->description = $description;
        $request->callback_url = $callbackUrl;
        $request->mobile = $mobile;
        $request->email = $email;

        $response = $zarinpal
            ->paymentGateway()
            ->request($request);

        return [
            'authority' => $response->authority,
            'payment_url' => $zarinpal
                ->paymentGateway()
                ->getRedirectUrl($response->authority),
        ];
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

        $sandbox = (bool) config('services.zarinpal.sandbox', true);

        $baseUrl = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://www.zarinpal.com/pg/StartPay/';

        return $baseUrl . trim($authority);
    }
    public function verifyPayment(
        int|float $amount,
        string $authority
    ): array {
        $merchantId = config('services.zarinpal.merchant_id');

        if (empty($merchantId) || $merchantId === 'YOUR_MERCHANT_ID') {
            throw new RuntimeException(
                'ZarinPal merchant ID is not configured.'
            );
        }

        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        if (empty(trim($authority))) {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        $options = new \ZarinPal\Sdk\Options();

        $zarinpal = new ZarinPal($options);

        $request = new VerifyRequest();

        $request->amount = (int) $amount;
        $request->authority = trim($authority);

        $response = $zarinpal
            ->paymentGateway()
            ->verify($request);

        /*
         * ZarinPal verification codes:
         *
         * 100 = Payment verified successfully.
         * 101 = Payment was already verified.
         *
         * Any other code means the payment must not be marked as paid.
         */
        if (!in_array((int) $response->code, [100, 101], true)) {
            throw new RuntimeException(
                'ZarinPal payment verification failed. Code: '
                . $response->code
                . ' - '
                . $response->message
            );
        }

        return [
            'authority' => $response->authority,
            'code' => $response->code,
            'message' => $response->message,
            'ref_id' => $response->ref_id,
            'card_pan' => $response->card_pan,
            'card_hash' => $response->card_hash,
            'fee_type' => $response->fee_type,
            'fee' => $response->fee,
        ];
    }

}
