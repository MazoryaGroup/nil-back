<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Notification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class SendDailyReminders extends Command
{
    /**
     * Command name.
     */
    protected $signature = 'app:send-daily-reminders';

    /**
     * Command description.
     */
    protected $description = 'Send booking reminders to clients before their appointments';

    public function handle(NotificationService $notificationService): int
    {
        $now = now();

        $this->info(
            'Checking booking reminders at ' .
            $now->format('Y-m-d H:i:s')
        );

        /*
        |--------------------------------------------------------------------------
        | Active Bookings
        |--------------------------------------------------------------------------
        |
        | فقط رزروهایی بررسی می‌شوند که هنوز معتبر هستند.
        |
        */

        $bookings = Booking::query()
            ->with('client')
            ->whereIn('status', [
                'awaiting_payment',
                'confirmed',
            ])
            ->whereDate(
                'booking_date',
                '>=',
                $now->toDateString()
            )
            ->get();

        $createdCount = 0;

        foreach ($bookings as $booking) {

            if (!$booking->client) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Appointment Date Time
            |--------------------------------------------------------------------------
            */

            try {
                $appointmentAt = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $booking->booking_date->format('Y-m-d')
                    . ' '
                    . $booking->start_time,
                    config('app.timezone')
                );
            } catch (\Throwable $e) {

                $this->warn(
                    "Invalid date/time for booking #{$booking->id}"
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Ignore Past Appointments
            |--------------------------------------------------------------------------
            */

            if ($appointmentAt->lte($now)) {
                continue;
            }

            $minutesUntilAppointment = $now->diffInMinutes(
                $appointmentAt,
                false
            );

            /*
            |--------------------------------------------------------------------------
            | 24 Hour Reminder
            |--------------------------------------------------------------------------
            |
            | Scheduler ممکن است دقیقاً سر 24 ساعت اجرا نشود.
            | بنابراین یک بازه در نظر می‌گیریم.
            |
            | 23h45m تا 24h15m
            |
            */

            if (
                $minutesUntilAppointment >= 1425
                && $minutesUntilAppointment <= 1455
            ) {
                $created = $this->createReminderIfNeeded(
                    notificationService: $notificationService,
                    booking: $booking,
                    type: 'booking_reminder_24h',
                    title: 'Appointment Reminder',
                    message:
                    'Your appointment is tomorrow at '
                    . $appointmentAt->format('H:i')
                    . '.'
                );

                if ($created) {
                    $createdCount++;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2 Hour Reminder
            |--------------------------------------------------------------------------
            |
            | 1h45m تا 2h15m
            |
            */

            if (
                $minutesUntilAppointment >= 105
                && $minutesUntilAppointment <= 135
            ) {
                $created = $this->createReminderIfNeeded(
                    notificationService: $notificationService,
                    booking: $booking,
                    type: 'booking_reminder_2h',
                    title: 'Appointment Reminder',
                    message:
                    'Your appointment is in about 2 hours at '
                    . $appointmentAt->format('H:i')
                    . '.'
                );

                if ($created) {
                    $createdCount++;
                }
            }
        }

        $this->info(
            "Booking reminder check completed. Created: {$createdCount}"
        );

        return self::SUCCESS;
    }

    /**
     * Create reminder only once for each booking/type.
     */
    private function createReminderIfNeeded(
        NotificationService $notificationService,
        Booking $booking,
        string $type,
        string $title,
        string $message
    ): bool {

        /*
        |--------------------------------------------------------------------------
        | Duplicate Protection
        |--------------------------------------------------------------------------
        */

        $alreadyExists = Notification::query()
            ->where('booking_id', $booking->id)
            ->where('client_id', $booking->client_id)
            ->where('type', $type)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Internal Notification
        |--------------------------------------------------------------------------
        */

        $notificationService->notifyClient(
            client: $booking->client,
            type: $type,
            title: $title,
            message: $message,
            booking: $booking
        );

        /*
        |--------------------------------------------------------------------------
        | Send SMS Reminder
        |--------------------------------------------------------------------------
        */

        $client = $booking->client;

        if ($client && !empty($client->phone)) {

            $smsAlreadySent = $booking->smsLogs()
                ->where('type', $type)
                ->where('status', 'sent')
                ->exists();

            if (!$smsAlreadySent) {

                try {

                    $time = Carbon::createFromFormat(
                        'H:i:s',
                        $booking->start_time
                    )->format('H:i');

                    if ($type === 'booking_reminder_24h') {

                        app(SmsService::class)->sendReminder24h(
                            phone: $client->phone,

                            date: Jalalian::fromCarbon(
                                $booking->booking_date
                            )->format('Y/m/d'),

                            time: $time,

                            booking: $booking,
                            client: $client
                        );

                    } elseif ($type === 'booking_reminder_2h') {

                        app(SmsService::class)->sendReminder2h(
                            phone: $client->phone,
                            time: $time,
                            booking: $booking,
                            client: $client
                        );
                    }

                } catch (\Throwable $e) {

                    Log::error('Booking reminder SMS failed', [
                        'booking_id' => $booking->id,
                        'client_id' => $booking->client_id,
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info(
            "Created {$type} for booking #{$booking->id}"
        );

        return true;
    }
}
