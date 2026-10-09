<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Client;
use App\Models\SmsLog;
use App\Models\User;
use Cryptommer\Smsir\Objects\Parameters;
use Cryptommer\Smsir\Smsir;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SmsService
{
    /**
     * Send SMS.ir Verify/Template message.
     */
    public function sendTemplate(
        string $phone,
        int $templateId,
        array $parameters,
        string $type,
        ?Booking $booking = null,
        ?Client $client = null,
        ?User $user = null,
        ?string $message = null
    ): SmsLog {

        $smsLog = SmsLog::create([
            'booking_id' => $booking?->id,
            'client_id' => $client?->id,
            'user_id' => $user?->id,
            'phone' => $phone,
            'type' => $type,
            'message' => $message,
            'provider' => 'sms.ir',
            'provider_message_id' => null,
            'status' => 'pending',
            'error_message' => null,
            'sent_at' => null,
        ]);

        try {
            if ($templateId <= 0) {
                throw new RuntimeException(
                    'SMS.ir template ID is not configured.'
                );
            }

            $normalizedPhone = $this->normalizePhone($phone);

            $smsParameters = [];

            foreach ($parameters as $name => $value) {
                $smsParameters[] = new Parameters(
                    (string) $name,
                    (string) $value
                );
            }

            $response = Smsir::Send()->Verify(
                $normalizedPhone,
                $templateId,
                $smsParameters
            );

            /*
            |--------------------------------------------------------------------------
            | Validate Provider Response
            |--------------------------------------------------------------------------
            */

            $responseData = $this->responseToArray($response);

            $providerStatus = data_get($responseData, 'status');

            if ($providerStatus === false || $providerStatus === 0) {
                throw new RuntimeException(
                    'SMS.ir rejected the message: '
                    . json_encode(
                        $responseData,
                        JSON_UNESCAPED_UNICODE
                    )
                );
            }

            $providerMessageId = $this->extractMessageId($response);

            $smsLog->update([
                'provider_message_id' => $providerMessageId,
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]);

            Log::info('SMS.ir message accepted', [
                'sms_log_id' => $smsLog->id,
                'type' => $type,
                'template_id' => $templateId,
                'provider_message_id' => $providerMessageId,
            ]);

            return $smsLog->refresh();

        } catch (Throwable $e) {

            $providerBody = null;
            $httpStatus = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $httpStatus = $e->getResponse()->getStatusCode();
                $providerBody = (string) $e->getResponse()->getBody();
            }

            $errorMessage = $e->getMessage();

            if ($providerBody !== null && $providerBody !== '') {
                $errorMessage .= ' | SMS.ir response: ' . $providerBody;
            }

            $smsLog->update([
                'status' => 'failed',
                'error_message' => mb_substr($errorMessage, 0, 2000),
                'sent_at' => null,
            ]);

            Log::error('SMS.ir send failed', [
                'sms_log_id' => $smsLog->id,
                'type' => $type,
                'template_id' => $templateId,
                'http_status' => $httpStatus,
                'provider_response' => $providerBody,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send OTP.
     */
    public function sendOtp(
        string $phone,
        string $code,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.otp'),
            parameters: [
                'Code' => $code,
            ],
            type: 'otp',
            client: $client,
            message: 'OTP verification code'
        );
    }

    /**
     * Send ZarinPal payment link.
     *
     * SMS.ir template 254464:
     *
     * این لینک پرداخت
     * #LINK#
     */
    public function sendPaymentLink(
        Booking $booking,
        string $paymentUrl
    ): SmsLog {

        $client = $booking->client;

        if (!$client || empty($client->phone)) {
            throw new RuntimeException(
                'Booking client phone number not found.'
            );
        }

        $templateId = (int) config(
            'services.smsir.templates.payment_link'
        );

        if ($templateId <= 0) {
            throw new RuntimeException(
                'Payment link SMS template is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Payment URL
        |--------------------------------------------------------------------------
        */

        $paymentUrl = trim($paymentUrl);

        if (
            !filter_var($paymentUrl, FILTER_VALIDATE_URL)
            || parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https'
        ) {
            throw new RuntimeException(
                'Invalid payment URL.'
            );
        }

        $host = strtolower(
            (string) parse_url($paymentUrl, PHP_URL_HOST)
        );

        $allowedHosts = [
            'payment.zarinpal.com',
            'www.zarinpal.com',
        ];

        if (!in_array($host, $allowedHosts, true)) {
            throw new RuntimeException(
                'Payment URL host is not allowed.'
            );
        }

        return $this->sendTemplate(
            phone: $client->phone,
            templateId: $templateId,
            parameters: [
                'LINK' => $paymentUrl,
            ],
            type: 'payment_link',
            booking: $booking,
            client: $client,
            message: 'ZarinPal payment link for booking #' . $booking->id
        );
    }

    /**
     * Booking Confirmed.
     */
    public function sendBookingConfirmed(
        string $phone,
        string $date,
        string $time,
        ?Booking $booking = null,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.booking_confirmed'),
            parameters: [
                'DATE' => $date,
                'TIME' => $time,
            ],
            type: 'booking_confirmed',
            booking: $booking,
            client: $client,
            message: 'Booking confirmed'
        );
    }

    /**
     * Booking Cancelled.
     */
    public function sendBookingCancelled(
        string $phone,
        string $date,
        string $time,
        ?Booking $booking = null,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.booking_cancelled'),
            parameters: [
                'DATE' => $date,
                'TIME' => $time,
            ],
            type: 'booking_cancelled',
            booking: $booking,
            client: $client,
            message: 'Booking cancelled'
        );
    }

    /**
     * Booking Rescheduled.
     */
    public function sendBookingRescheduled(
        string $phone,
        string $date,
        string $time,
        ?Booking $booking = null,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.booking_rescheduled'),
            parameters: [
                'DATE' => $date,
                'TIME' => $time,
            ],
            type: 'booking_rescheduled',
            booking: $booking,
            client: $client,
            message: 'Booking rescheduled'
        );
    }

    /**
     * Reminder - 24 Hours Before.
     */
    public function sendReminder24h(
        string $phone,
        string $date,
        string $time,
        ?Booking $booking = null,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.reminder_24h'),
            parameters: [
                'DATE' => $date,
                'TIME' => $time,
            ],
            type: 'booking_reminder_24h',
            booking: $booking,
            client: $client,
            message: 'Booking reminder 24 hours before'
        );
    }

    /**
     * Reminder - 2 Hours Before.
     */
    public function sendReminder2h(
        string $phone,
        string $time,
        ?Booking $booking = null,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.reminder_2h'),
            parameters: [
                'TIME' => $time,
            ],
            type: 'booking_reminder_2h',
            booking: $booking,
            client: $client,
            message: 'Booking reminder 2 hours before'
        );
    }

    /**
     * Payment Success.
     */
    public function sendPaymentSuccess(
        string $phone,
        string|int|float $amount,
        ?Booking $booking = null,
        ?Client $client = null
    ): SmsLog {

        return $this->sendTemplate(
            phone: $phone,
            templateId: (int) config('services.smsir.templates.payment_success'),
            parameters: [
                'AMOUNT' => $amount,
            ],
            type: 'payment_success',
            booking: $booking,
            client: $client,
            message: 'Payment successful'
        );
    }

    /**
     * Normalize Iranian mobile number.
     *
     * Supported inputs:
     * 09123456789
     * 989123456789
     * 00989123456789
     * 9123456789
     */


    /**
     * Send welcome SMS with referral code.
     */
    public function sendWelcome(
        Client $client
    ): SmsLog {

        if (empty($client->phone)) {
            throw new \RuntimeException(
                'Client phone number not found.'
            );
        }

        if (empty($client->referral_code)) {
            throw new \RuntimeException(
                'Client referral code not found.'
            );
        }

        return $this->sendTemplate(
            phone: $client->phone,
            templateId: (int) config('services.smsir.templates.welcome'),
            parameters: [
                'REFERRAL' => $client->referral_code,
            ],
            type: 'welcome',
            client: $client,
            message: 'Welcome to NIL - Referral code: ' . $client->referral_code
        );
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '0098')) {
            $phone = '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '98')) {
            $phone = '0' . substr($phone, 2);
        } elseif (
            strlen($phone) === 10
            && str_starts_with($phone, '9')
        ) {
            $phone = '0' . $phone;
        }

        if (!preg_match('/^09\d{9}$/', $phone)) {
            throw new RuntimeException(
                'Invalid Iranian mobile number.'
            );
        }

        return $phone;
    }

    /**
     * Convert SMS.ir response into array.
     */
    private function responseToArray(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            return json_decode(
                json_encode($response),
                true
            ) ?: [];
        }

        return [];
    }

    /**
     * Extract provider message ID.
     */
    private function extractMessageId(mixed $response): ?string
    {
        $data = $this->responseToArray($response);

        foreach ([
                     'messageId',
                     'message_id',
                     'MessageId',
                     'id',
                     'data.messageId',
                     'data.message_id',
                     'data.MessageId',
                     'data.id',
                 ] as $key) {

            $value = data_get($data, $key);

            if ($value !== null && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
