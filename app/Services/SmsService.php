<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Client;
use App\Models\SmsLog;
use App\Models\User;
use Cryptommer\Smsir\Objects\Parameters;
use Cryptommer\Smsir\Smsir;
use Illuminate\Support\Facades\Log;
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

            $smsParameters = [];

            foreach ($parameters as $name => $value) {
                $smsParameters[] = new Parameters(
                    (string) $name,
                    (string) $value
                );
            }

            $response = Smsir::Send()->Verify(
                $this->normalizePhone($phone),
                $templateId,
                $smsParameters
            );

            $providerMessageId = $this->extractMessageId($response);

            $smsLog->update([
                'provider_message_id' => $providerMessageId,
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]);

            return $smsLog->refresh();

        } catch (Throwable $e) {

            $smsLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('SMS.ir send failed', [
                'sms_log_id' => $smsLog->id,
                'phone' => $phone,
                'type' => $type,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * OTP
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
     * Booking Confirmed
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
     * Booking Cancelled
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
     * Booking Rescheduled
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
     * Reminder - 24 Hours Before
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
     * Reminder - 2 Hours Before
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
     * Payment Success
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
     * Normalize Iranian phone number for SMS.ir.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '0098')) {
            $phone = substr($phone, 4);
        } elseif (str_starts_with($phone, '98')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Extract SMS.ir message ID safely.
     */
    private function extractMessageId(mixed $response): ?string
    {
        if (is_object($response)) {

            foreach ([
                         'messageId',
                         'message_id',
                         'MessageId',
                         'id',
                     ] as $property) {

                if (isset($response->{$property})) {
                    return (string) $response->{$property};
                }
            }
        }

        if (is_array($response)) {

            foreach ([
                         'messageId',
                         'message_id',
                         'MessageId',
                         'id',
                     ] as $key) {

                if (isset($response[$key])) {
                    return (string) $response[$key];
                }
            }
        }

        return null;
    }
}
