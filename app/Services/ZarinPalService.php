<?php

namespace App\Services;

use RuntimeException;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\RequestRequest;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;

class ZarinPalService
{
    /**
     * Build SDK with Laravel configuration.
     */
    private function gateway(): ZarinPal
    {
        $merchantId = trim(
            (string) config('services.zarinpal.merchant_id', '')
        );

        if (
            $merchantId === ''
            || $merchantId === 'YOUR_MERCHANT_ID'
            || $merchantId === 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
        ) {
            throw new RuntimeException(
                'ZarinPal merchant ID is not configured.'
            );
        }

        $options = new Options([
            'merchant_id' => $merchantId,
            'sandbox' => filter_var(
                config('services.zarinpal.sandbox', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        ]);

        return new ZarinPal($options);
    }

    /**
     * Create payment request.
     *
     * Amount must use the unit expected by
     * the configured ZarinPal merchant.
     */
    public function requestPayment(
        int|float $amount,
        string $callbackUrl,
        string $description,
        ?string $email = null,
        ?string $mobile = null
    ): array {

        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        if (trim($callbackUrl) === '') {
            throw new RuntimeException(
                'ZarinPal callback URL is required.'
            );
        }

        if (trim($description) === '') {
            throw new RuntimeException(
                'Payment description is required.'
            );
        }

        $zarinpal = $this->gateway();

        $request = new RequestRequest();

        $request->amount = (int) round($amount * 10);
        $request->description = $description;
        $request->callback_url = $callbackUrl;
        $request->mobile = $mobile;
        $request->email = $email;

        $response = $zarinpal
            ->paymentGateway()
            ->request($request);

        $authority = trim(
            (string) ($response->authority ?? '')
        );

        if ($authority === '') {
            throw new RuntimeException(
                'ZarinPal did not return a valid authority.'
            );
        }

        return [
            'authority' => $authority,
            'payment_url' => $zarinpal
                ->paymentGateway()
                ->getRedirectUrl($authority),
        ];
    }

    /**
     * Recover an existing payment URL
     * without requesting a new Authority.
     */
    public function getPaymentUrl(string $authority): string
    {
        $authority = trim($authority);

        if ($authority === '') {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        return $this->gateway()
            ->paymentGateway()
            ->getRedirectUrl($authority);
    }

    /**
     * Verify payment with the same SDK configuration.
     */
    public function verifyPayment(
        int|float $amount,
        string $authority
    ): array {

        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        $authority = trim($authority);

        if ($authority === '') {
            throw new RuntimeException(
                'ZarinPal authority is required.'
            );
        }

        $zarinpal = $this->gateway();

        $request = new VerifyRequest();

        $request->amount = (int) round($amount * 10);
        $request->authority = $authority;

        $response = $zarinpal
            ->paymentGateway()
            ->verify($request);

        $code = (int) ($response->code ?? 0);

        if (!in_array($code, [100, 101], true)) {
            throw new RuntimeException(
                'ZarinPal payment verification failed. Code: '
                . $code
                . ' - '
                . ($response->message ?? 'Unknown error')
            );
        }

        return [
            'authority' => $authority,
            'code' => $code,
            'message' => $response->message ?? null,
            'ref_id' => $response->ref_id ?? null,
            'card_pan' => $response->card_pan ?? null,
            'card_hash' => $response->card_hash ?? null,
            'fee_type' => $response->fee_type ?? null,
            'fee' => $response->fee ?? null,
        ];
    }
}
