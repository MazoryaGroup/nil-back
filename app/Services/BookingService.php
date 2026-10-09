<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\BookingService as BookingServiceModel;
use App\Models\DiscountCode;
use App\Models\DiscountUsage;
use App\Models\Service;
use App\Models\Staff;
use App\Services\ReferralRewardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\AccountingAuditLog;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class BookingService
{
    public function createBooking(
        int $clientId,
        string $date,
        array $services,
        ?string $notes = null,
        ?string $discountCode = null,
        bool $useReferralReward = false
    ): Booking {
        return DB::transaction(function () use (
            $clientId,
            $date,
            $services,
            $notes,
            $discountCode,
            $useReferralReward
        ) {
            if (empty($services)) {
                throw ValidationException::withMessages([
                    'services' => 'At least one service is required.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Discount Code and Referral Reward cannot be used together
            |--------------------------------------------------------------------------
            */

            if (
                $discountCode !== null &&
                trim($discountCode) !== '' &&
                $useReferralReward
            ) {
                throw ValidationException::withMessages([
                    'discount' =>
                        'Discount code and referral reward cannot be used together.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Initial Variables
            |--------------------------------------------------------------------------
            */

            $bookingServices = [];
            $pendingServices = [];

            $bookingStart = null;
            $bookingEnd = null;

            $subtotal = 0;
            $discountAmount = 0;
            $totalAmount = 0;
            $depositAmount = 0;

            $availabilityService = app(
                BookingAvailabilityService::class
            );

            /*
            |--------------------------------------------------------------------------
            | Validate Booking Services
            |--------------------------------------------------------------------------
            */

            foreach ($services as $item) {

                $serviceId = (int) ($item['service_id'] ?? 0);
                $staffId = (int) ($item['staff_id'] ?? 0);
                $startTime = $item['start_time'] ?? null;

                if (!$serviceId || !$staffId || !$startTime) {
                    throw ValidationException::withMessages([
                        'services' =>
                            'Each service must have service_id, staff_id and start_time.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Check Service
                |--------------------------------------------------------------------------
                */

                $service = Service::query()
                    ->where('id', $serviceId)
                    ->where('is_active', true)
                    ->first();

                if (!$service) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "Service {$serviceId} is not available.",
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Check Staff
                |--------------------------------------------------------------------------
                */

                $staff = Staff::query()
                    ->where('id', $staffId)
                    ->where('is_active', true)
                    ->first();

                if (!$staff) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "Staff {$staffId} is not available.",
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Check Staff Service
                |--------------------------------------------------------------------------
                */

                $staffService = $staff->staffServices()
                    ->where('service_id', $serviceId)
                    ->where('is_active', true)
                    ->first();

                if (!$staffService) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "This staff member cannot perform service {$serviceId}.",
                    ]);
                }

                $duration = (int) $service->duration;
                $price = (float) $service->price;
                $deposit = (float) $service->deposit_amount;

                if ($duration <= 0) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "Invalid duration for service {$serviceId}.",
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Validate Start Time
                |--------------------------------------------------------------------------
                */

                try {
                    $start = Carbon::createFromFormat(
                        '!H:i',
                        $startTime
                    );
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "Invalid start time: {$startTime}.",
                    ]);
                }

                if ($start->format('H:i') !== $startTime) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "Invalid start time: {$startTime}.",
                    ]);
                }

                $end = $start->copy()->addMinutes($duration);

                if (!$start->isSameDay($end)) {
                    throw ValidationException::withMessages([
                        'services' =>
                            'Service cannot extend into the next day.',
                    ]);
                }

                $normalizedStartTime = $start->format('H:i:s');
                $normalizedEndTime = $end->format('H:i:s');

                /*
                |--------------------------------------------------------------------------
                | Prevent Booking in the Past
                |--------------------------------------------------------------------------
                */

                $serviceDateTime = Carbon::createFromFormat(
                    '!Y-m-d H:i:s',
                    $date . ' ' . $normalizedStartTime
                );

                if ($serviceDateTime->lte(now())) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "The selected time {$startTime} must be in the future.",
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Check Staff Availability
                |--------------------------------------------------------------------------
                */

                $isAvailable = $availabilityService->isTimeAvailable(
                    $staffId,
                    $date,
                    $normalizedStartTime,
                    $normalizedEndTime
                );

                if (!$isAvailable) {
                    throw ValidationException::withMessages([
                        'services' =>
                            "The selected time {$startTime} is not available for service {$serviceId}.",
                    ]);
                }

                /*
|--------------------------------------------------------------------------
| Prevent Client Overlap Across Different Bookings
|--------------------------------------------------------------------------
*/

                $hasClientConflict = BookingServiceModel::query()
                    ->whereHas('booking', function ($query) use ($clientId, $date) {
                        $query->where('client_id', $clientId)
                            ->whereDate('booking_date', $date)
                            ->whereNotIn('status', [
                                'cancelled',
                                'rejected',
                                'no_show',
                            ]);
                    })
                    ->where('start_time', '<', $normalizedEndTime)
                    ->where('end_time', '>', $normalizedStartTime)
                    ->exists();

                if ($hasClientConflict) {
                    throw ValidationException::withMessages([
                        'services' =>
                            'You already have another booking at the selected time.',
                    ]);
                }
                /*
                |--------------------------------------------------------------------------
                | Prevent Overlapping Services for Same Client
                |--------------------------------------------------------------------------
                |
                | Different staff members are allowed,
                | but the client's services cannot overlap.
                |
                */

                foreach ($pendingServices as $pendingService) {

                    $pendingStart = Carbon::createFromFormat(
                        '!H:i:s',
                        $pendingService['start_time']
                    );

                    $pendingEnd = Carbon::createFromFormat(
                        '!H:i:s',
                        $pendingService['end_time']
                    );

                    $overlaps =
                        $start->lt($pendingEnd) &&
                        $end->gt($pendingStart);

                    if ($overlaps) {
                        throw ValidationException::withMessages([
                            'services' =>
                                'Selected services cannot overlap, even with different staff members.',
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Deposit
                |--------------------------------------------------------------------------
                */



                /*
                |--------------------------------------------------------------------------
                | Calculate Booking Start and End
                |--------------------------------------------------------------------------
                */

                if (
                    $bookingStart === null ||
                    $normalizedStartTime < $bookingStart
                ) {
                    $bookingStart = $normalizedStartTime;
                }

                if (
                    $bookingEnd === null ||
                    $normalizedEndTime > $bookingEnd
                ) {
                    $bookingEnd = $normalizedEndTime;
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Amounts
                |--------------------------------------------------------------------------
                */

                $subtotal += $price;
                $depositAmount += $deposit;

                /*
                |--------------------------------------------------------------------------
                | Prepare Booking Service
                |--------------------------------------------------------------------------
                */

                $bookingServiceData = [
                    'service_id' => $serviceId,
                    'staff_id' => $staffId,
                    'start_time' => $normalizedStartTime,
                    'end_time' => $normalizedEndTime,
                    'duration' => $duration,
                    'price' => $price,
                    'deposit_amount' => $deposit,
                ];

                $pendingServices[] = $bookingServiceData;
                $bookingServices[] = $bookingServiceData;
            }

            /*
            |--------------------------------------------------------------------------
            | Discount / Referral Reward
            |--------------------------------------------------------------------------
            */

            $discount = null;
            $referralRewardService = null;
            $referralRewardResult = null;

            /*
            |--------------------------------------------------------------------------
            | Generic Discount Code
            |--------------------------------------------------------------------------
            */

            if (
                $discountCode !== null &&
                trim($discountCode) !== ''
            ) {
                $normalizedDiscountCode = strtoupper(
                    trim($discountCode)
                );

                $discount = DiscountCode::query()
                    ->whereRaw(
                        'UPPER(code) = ?',
                        [$normalizedDiscountCode]
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$discount) {
                    throw ValidationException::withMessages([
                        'discount_code' => 'Invalid discount code.',
                    ]);
                }

                if (!$discount->is_active) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'This discount code is inactive.',
                    ]);
                }

                $now = now();

                if (
                    $discount->starts_at !== null &&
                    $now->lt($discount->starts_at)
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'This discount code is not active yet.',
                    ]);
                }

                if (
                    $discount->expires_at !== null &&
                    $now->gt($discount->expires_at)
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'This discount code has expired.',
                    ]);
                }

                if (
                    $discount->min_order_amount !== null &&
                    $subtotal < (float) $discount->min_order_amount
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'The order amount is below the minimum amount required for this discount code.',
                    ]);
                }

                if (
                    $discount->usage_limit !== null &&
                    $discount->usage_count >= $discount->usage_limit
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'This discount code has reached its usage limit.',
                    ]);
                }

                if ($discount->usage_limit_per_client !== null) {

                    $clientUsageCount = DiscountUsage::query()
                        ->where('discount_code_id', $discount->id)
                        ->where('client_id', $clientId)
                        ->count();

                    if (
                        $clientUsageCount >=
                        $discount->usage_limit_per_client
                    ) {
                        throw ValidationException::withMessages([
                            'discount_code' =>
                                'You have already reached the usage limit for this discount code.',
                        ]);
                    }
                }

                if ($discount->type === 'percentage') {
                    $discountAmount =
                        $subtotal * ((float) $discount->value / 100);
                } else {
                    $discountAmount = (float) $discount->value;
                }

                if (
                    $discount->max_discount_amount !== null &&
                    $discountAmount >
                    (float) $discount->max_discount_amount
                ) {
                    $discountAmount =
                        (float) $discount->max_discount_amount;
                }

                if ($discountAmount > $subtotal) {
                    $discountAmount = $subtotal;
                }

                $discountAmount = round($discountAmount, 2);
            }

            /*
            |--------------------------------------------------------------------------
            | Referral Reward
            |--------------------------------------------------------------------------
            */

            if ($useReferralReward) {

                $referralRewardService = app(
                    ReferralRewardService::class
                );

                $referralRewardResult =
                    $referralRewardService->calculateDiscount(
                        clientId: $clientId,
                        orderAmount: $subtotal
                    );

                if (
                    !$referralRewardResult['eligible'] ||
                    !$referralRewardResult['rule']
                ) {
                    throw ValidationException::withMessages([
                        'use_referral_reward' =>
                            'You are not eligible for a referral reward.',
                    ]);
                }

                $discountAmount =
                    (float) $referralRewardResult['discount_amount'];
            }

            /*
            |--------------------------------------------------------------------------
            | Final Total
            |--------------------------------------------------------------------------
            */

            $totalAmount = $subtotal - $discountAmount;

            if ($totalAmount < 0) {
                $totalAmount = 0;
            }

            $totalAmount = round($totalAmount, 2);

            /*
            |--------------------------------------------------------------------------
            | Create Booking
            |--------------------------------------------------------------------------
            */

            $booking = Booking::create([
                'client_id' => $clientId,
                'booking_date' => $date,
                'start_time' => $bookingStart,
                'end_time' => $bookingEnd,

                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,

                'deposit_amount' => $depositAmount,
                'paid_amount' => 0,

                'status' => 'awaiting_payment',
                'payment_status' => 'pending',

                'notes' => $notes,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Booking Services
            |--------------------------------------------------------------------------
            */

            foreach ($bookingServices as $bookingService) {
                BookingServiceModel::create([
                    'booking_id' => $booking->id,
                    ...$bookingService,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Record Generic Discount Usage
            |--------------------------------------------------------------------------
            */

            if (
                $discount &&
                $discountAmount > 0
            ) {
                DiscountUsage::create([
                    'discount_code_id' => $discount->id,
                    'client_id' => $clientId,
                    'booking_id' => $booking->id,
                    'discount_amount' => $discountAmount,
                ]);

                $discount->increment('usage_count');
            }

            /*
            |--------------------------------------------------------------------------
            | Record Referral Reward Usage
            |--------------------------------------------------------------------------
            */

            if (
                $useReferralReward &&
                $referralRewardResult &&
                $referralRewardResult['eligible'] &&
                $referralRewardResult['rule']
            ) {
                $referralRewardService->recordUsage(
                    clientId: $clientId,
                    bookingId: $booking->id,
                    orderAmount: $subtotal
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Convert Booking Hold
            |--------------------------------------------------------------------------
            */

            BookingHold::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'converted',
                ]);

            /*
            |--------------------------------------------------------------------------
            | Return Booking
            |--------------------------------------------------------------------------
            */

            return $booking->load([
                'bookingServices.service',
                'bookingServices.staff',
                'payments',
            ]);
        });
    }
    public function cancelBooking(
        int $bookingId,
        int $clientId,
        ?string $reason = null
    ): Booking {
        return DB::transaction(function () use (
            $bookingId,
            $clientId,
            $reason
        ) {
            $booking = Booking::query()
                ->where('id', $bookingId)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking not found.',
                ]);
            }

            if (in_array($booking->status, [
                'cancelled',
                'completed',
                'rejected',
                'no_show',
            ], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking cannot be cancelled.',
                ]);
            }

            $bookingStart = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $booking->booking_date->format('Y-m-d')
                . ' '
                . $booking->start_time
            );

            $hoursUntilBooking = now()->diffInHours(
                $bookingStart,
                false
            );

            if ($hoursUntilBooking < 24) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'Booking can only be cancelled at least 24 hours before the appointment.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Store old values for Audit Log
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'status' => $booking->status,
                'cancelled_at' => $booking->cancelled_at?->format('Y-m-d H:i:s'),
                'cancellation_reason' => $booking->cancellation_reason,
                'paid_amount' => (float) $booking->paid_amount,
                'payment_status' => $booking->payment_status,
            ];

            /*
            |--------------------------------------------------------------------------
            | Cancel Booking
            |--------------------------------------------------------------------------
            */

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Release Active Holds
            |--------------------------------------------------------------------------
            */

            BookingHold::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'released',
                ]);

            $booking->refresh();
            /*
|--------------------------------------------------------------------------
| Client Notification
|--------------------------------------------------------------------------
*/

            $booking->notifications()->create([
                'user_id' => null,
                'client_id' => $booking->client_id,
                'type' => 'booking_cancelled',
                'title' => 'Booking Cancelled',
                'message' => 'Your booking has been cancelled successfully.',
                'is_read' => false,
                'read_at' => null,
            ]);

            /*
|--------------------------------------------------------------------------
| Booking Cancelled SMS
|--------------------------------------------------------------------------
*/

            $client = $booking->client;

            if ($client && !empty($client->phone)) {

                $smsAlreadySent = $booking->smsLogs()
                    ->where('type', 'booking_cancelled')
                    ->where('status', 'sent')
                    ->exists();

                if (!$smsAlreadySent) {
                    try {
                        app(SmsService::class)->sendBookingCancelled(
                            phone: $client->phone,

                            date: Jalalian::fromCarbon(
                                $booking->booking_date
                            )->format('Y/m/d'),

                            time: Carbon::createFromFormat(
                                'H:i:s',
                                $booking->start_time
                            )->format('H:i'),

                            booking: $booking,
                            client: $client
                        );

                    } catch (\Throwable $e) {

                        Log::error('Booking cancelled SMS failed', [
                            'booking_id' => $booking->id,
                            'client_id' => $client->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            AccountingAuditLog::create([
                'user_id' => null,

                'action' => 'booking_cancelled',

                'entity_type' => 'booking',
                'entity_id' => $booking->id,

                'booking_id' => $booking->id,
                'client_id' => $booking->client_id,

                'description' => $reason
                    ?: 'Booking cancelled by client.',

                'old_values' => $oldValues,

                'new_values' => [
                    'status' => $booking->status,
                    'cancelled_at' => $booking->cancelled_at?->format('Y-m-d H:i:s'),
                    'cancellation_reason' => $booking->cancellation_reason,
                    'paid_amount' => (float) $booking->paid_amount,
                    'payment_status' => $booking->payment_status,
                    'refund_created' => false,
                ],

                'ip_address' => request()?->ip(),

                'user_agent' => request()?->userAgent(),

                'created_at' => now(),
            ]);

            return $booking->load([
                'bookingServices.service',
                'bookingServices.staff',
                'payments',
            ]);
        });
    }

    public function rescheduleBooking(
        int $bookingId,
        int $clientId,
        string $newDate,
        string $newStartTime
    ): Booking {
        return DB::transaction(function () use (
            $bookingId,
            $clientId,
            $newDate,
            $newStartTime
        ) {
            /*
            |--------------------------------------------------------------------------
            | Find Booking
            |--------------------------------------------------------------------------
            */

            $booking = Booking::query()
                ->where('id', $bookingId)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking not found.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Check Booking Status
            |--------------------------------------------------------------------------
            */

            if (in_array($booking->status, [
                'cancelled',
                'completed',
                'rejected',
                'no_show',
            ], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking cannot be rescheduled.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Current Booking Date / Time
            |--------------------------------------------------------------------------
            */

            $bookingStart = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $booking->booking_date->format('Y-m-d')
                . ' '
                . $booking->start_time
            );

            /*
            |--------------------------------------------------------------------------
            | 24 Hour Rule
            |--------------------------------------------------------------------------
            */

            $hoursUntilBooking = now()->diffInHours(
                $bookingStart,
                false
            );

            if ($hoursUntilBooking < 24) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'Booking can only be rescheduled at least 24 hours before the appointment.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate New Date
            |--------------------------------------------------------------------------
            */

            try {
                $parsedNewDate = Carbon::createFromFormat(
                    'Y-m-d',
                    $newDate
                );
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'booking_date' =>
                        "Invalid booking date: {$newDate}.",
                ]);
            }

            if ($parsedNewDate->format('Y-m-d') !== $newDate) {
                throw ValidationException::withMessages([
                    'booking_date' =>
                        "Invalid booking date: {$newDate}.",
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate New Start Time
            |--------------------------------------------------------------------------
            */

            try {
                $newTime = Carbon::createFromFormat(
                    'H:i',
                    $newStartTime
                );
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'start_time' =>
                        "Invalid start time: {$newStartTime}.",
                ]);
            }

            if ($newTime->format('H:i') !== $newStartTime) {
                throw ValidationException::withMessages([
                    'start_time' =>
                        "Invalid start time: {$newStartTime}.",
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Rescheduling To Past
            |--------------------------------------------------------------------------
            */

            try {
                $newBookingDateTime = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $newDate . ' ' . $newStartTime
                );
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'booking_date' =>
                        'Invalid booking date or time.',
                ]);
            }

            if ($newBookingDateTime->lte(now())) {
                throw ValidationException::withMessages([
                    'booking_date' =>
                        'The new booking date and time must be in the future.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Get Booking Services
            |--------------------------------------------------------------------------
            */

            $bookingServices = $booking->bookingServices()
                ->orderBy('start_time')
                ->get();

            if ($bookingServices->isEmpty()) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking has no services.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Save Old Values For Audit
            |--------------------------------------------------------------------------
            */

            $oldValues = [
                'booking_date' =>
                    $booking->booking_date->format('Y-m-d'),

                'start_time' =>
                    $booking->start_time,

                'end_time' =>
                    $booking->end_time,

                'status' =>
                    $booking->status,

                'payment_status' =>
                    $booking->payment_status,

                'paid_amount' =>
                    (float) $booking->paid_amount,

                'services' => $bookingServices
                    ->map(function ($service) {
                        return [
                            'booking_service_id' =>
                                $service->id,

                            'service_id' =>
                                $service->service_id,

                            'staff_id' =>
                                $service->staff_id,

                            'start_time' =>
                                $service->start_time,

                            'end_time' =>
                                $service->end_time,
                        ];
                    })
                    ->values()
                    ->toArray(),
            ];

            /*
            |--------------------------------------------------------------------------
            | Calculate Time Offset
            |--------------------------------------------------------------------------
            */

            $oldBookingStart = Carbon::createFromFormat(
                'H:i:s',
                $booking->start_time
            );

            $newBookingStart = Carbon::createFromFormat(
                'H:i',
                $newStartTime
            );

            $offsetMinutes = $oldBookingStart->diffInMinutes(
                $newBookingStart,
                false
            );

            /*
            |--------------------------------------------------------------------------
            | Availability Service
            |--------------------------------------------------------------------------
            */

            $availabilityService = app(
                BookingAvailabilityService::class
            );

            $newBookingServices = [];

            /*
            |--------------------------------------------------------------------------
            | Check Every Service
            |--------------------------------------------------------------------------
            */

            foreach ($bookingServices as $bookingService) {

                $serviceStart = Carbon::createFromFormat(
                    'H:i:s',
                    $bookingService->start_time
                );

                $serviceEnd = Carbon::createFromFormat(
                    'H:i:s',
                    $bookingService->end_time
                );

                $newServiceStart = $serviceStart
                    ->copy()
                    ->addMinutes($offsetMinutes);

                $newServiceEnd = $serviceEnd
                    ->copy()
                    ->addMinutes($offsetMinutes);

                $isAvailable = $availabilityService
                    ->isTimeAvailable(
                        $bookingService->staff_id,
                        $newDate,
                        $newServiceStart->format('H:i:s'),
                        $newServiceEnd->format('H:i:s'),
                        $booking->id
                    );

                if (!$isAvailable) {
                    throw ValidationException::withMessages([
                        'booking' =>
                            "The selected time {$newServiceStart->format('H:i')} is not available.",
                    ]);
                }

                $newBookingServices[] = [
                    'id' =>
                        $bookingService->id,

                    'service_id' =>
                        $bookingService->service_id,

                    'staff_id' =>
                        $bookingService->staff_id,

                    'start_time' =>
                        $newServiceStart->format('H:i:s'),

                    'end_time' =>
                        $newServiceEnd->format('H:i:s'),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate New Booking End
            |--------------------------------------------------------------------------
            */

            $newBookingEnd = Carbon::createFromFormat(
                'H:i:s',
                $booking->end_time
            )->addMinutes($offsetMinutes);

            /*
            |--------------------------------------------------------------------------
            | Update Booking
            |--------------------------------------------------------------------------
            */

            $booking->update([
                'booking_date' =>
                    $newDate,

                'start_time' =>
                    $newBookingStart->format('H:i:s'),

                'end_time' =>
                    $newBookingEnd->format('H:i:s'),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Booking Services
            |--------------------------------------------------------------------------
            */

            foreach ($newBookingServices as $item) {

                BookingServiceModel::query()
                    ->where(
                        'id',
                        $item['id']
                    )
                    ->where(
                        'booking_id',
                        $booking->id
                    )
                    ->update([
                        'start_time' =>
                            $item['start_time'],

                        'end_time' =>
                            $item['end_time'],
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Release Old Active Holds
            |--------------------------------------------------------------------------
            */

            BookingHold::query()
                ->where(
                    'booking_id',
                    $booking->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->update([
                    'status' => 'released',
                ]);

            /*
            |--------------------------------------------------------------------------
            | Refresh Booking
            |--------------------------------------------------------------------------
            */

            $booking->refresh();

            /*
            |--------------------------------------------------------------------------
            | Client Notification
            |--------------------------------------------------------------------------
            */

            $booking->notifications()->create([
                'user_id' => null,
                'client_id' => $booking->client_id,
                'type' => 'booking_rescheduled',
                'title' => 'Booking Rescheduled',
                'message' =>
                    'Your booking has been rescheduled to '
                    . $booking->booking_date->format('Y-m-d')
                    . ' at '
                    . Carbon::createFromFormat(
                        'H:i:s',
                        $booking->start_time
                    )->format('H:i')
                    . '.',
                'is_read' => false,
                'read_at' => null,
            ]);
            /*
|--------------------------------------------------------------------------
| Booking Rescheduled SMS
|--------------------------------------------------------------------------
*/

            $client = $booking->client;

            if ($client && !empty($client->phone)) {

                try {
                    app(SmsService::class)->sendBookingRescheduled(
                        phone: $client->phone,

                        date: Jalalian::fromCarbon(
                            $booking->booking_date
                        )->format('Y/m/d'),

                        time: Carbon::createFromFormat(
                            'H:i:s',
                            $booking->start_time
                        )->format('H:i'),

                        booking: $booking,
                        client: $client
                    );

                } catch (\Throwable $e) {

                    Log::error('Booking rescheduled SMS failed', [
                        'booking_id' => $booking->id,
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Accounting Audit Log
            |--------------------------------------------------------------------------
            */

            AccountingAuditLog::create([
                'user_id' =>
                    null,

                'action' =>
                    'booking_rescheduled',

                'entity_type' =>
                    'booking',

                'entity_id' =>
                    $booking->id,

                'booking_id' =>
                    $booking->id,

                'client_id' =>
                    $booking->client_id,

                'description' =>
                    'Booking rescheduled by client.',

                'old_values' =>
                    $oldValues,

                'new_values' => [
                    'booking_date' =>
                        $booking->booking_date->format('Y-m-d'),

                    'start_time' =>
                        $booking->start_time,

                    'end_time' =>
                        $booking->end_time,

                    'status' =>
                        $booking->status,

                    'payment_status' =>
                        $booking->payment_status,

                    'paid_amount' =>
                        (float) $booking->paid_amount,

                    'services' =>
                        $newBookingServices,
                ],

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),

                'created_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Return Booking
            |--------------------------------------------------------------------------
            */

            return $booking->fresh([
                'bookingServices.service',
                'bookingServices.staff',
                'payments',
            ]);
        });
    }
}
