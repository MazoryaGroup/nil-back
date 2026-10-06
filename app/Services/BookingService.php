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
             * Discount Code and Referral Reward
             * cannot be used together.
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

            $bookingServices = [];
            $bookingStart = null;
            $bookingEnd = null;

            $subtotal = 0;
            $discountAmount = 0;
            $totalAmount = 0;
            $depositAmount = 0;

            $pendingServices = [];

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

                $duration = (int) $staffService->duration;
                $price = (float) $staffService->price;

                try {
                    $start = Carbon::createFromFormat(
                        'H:i',
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

                $normalizedStartTime =
                    $start->format('H:i:s');

                $normalizedEndTime =
                    $end->format('H:i:s');

                $availabilityService = app(
                    BookingAvailabilityService::class
                );

                $isAvailable =
                    $availabilityService->isTimeAvailable(
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

                foreach ($pendingServices as $pendingService) {
                    if (
                        $pendingService['staff_id'] !==
                        $staffId
                    ) {
                        continue;
                    }

                    $pendingStart =
                        Carbon::createFromFormat(
                            'H:i:s',
                            $pendingService['start_time']
                        );

                    $pendingEnd =
                        Carbon::createFromFormat(
                            'H:i:s',
                            $pendingService['end_time']
                        );

                    $overlaps =
                        $start->lt($pendingEnd)
                        && $end->gt($pendingStart);

                    if ($overlaps) {
                        throw ValidationException::withMessages([
                            'services' =>
                                'Service overlaps with another service for the selected staff member.',
                        ]);
                    }
                }

                $deposit =
                    (float) $service->deposit_amount;

                if (
                    $bookingStart === null ||
                    $normalizedStartTime < $bookingStart
                ) {
                    $bookingStart =
                        $normalizedStartTime;
                }

                if (
                    $bookingEnd === null ||
                    $normalizedEndTime > $bookingEnd
                ) {
                    $bookingEnd =
                        $normalizedEndTime;
                }

                $subtotal += $price;
                $depositAmount += $deposit;

                $bookingServiceData = [
                    'service_id' => $serviceId,
                    'staff_id' => $staffId,
                    'start_time' => $normalizedStartTime,
                    'end_time' => $normalizedEndTime,
                    'duration' => $duration,
                    'price' => $price,
                    'deposit_amount' => $deposit,
                ];

                $pendingServices[] =
                    $bookingServiceData;

                $bookingServices[] =
                    $bookingServiceData;
            }

            /*
             * ---------------------------------------------------------
             * DISCOUNT / REFERRAL REWARD
             * ---------------------------------------------------------
             */

            $discount = null;
            $referralRewardService = null;
            $referralRewardResult = null;

            /*
             * Generic Discount Code
             */
            if (
                $discountCode !== null &&
                trim($discountCode) !== ''
            ) {
                $normalizedDiscountCode =
                    strtoupper(
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
                        'discount_code' =>
                            'Invalid discount code.',
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
                    $subtotal <
                    (float) $discount->min_order_amount
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'The order amount is below the minimum amount required for this discount code.',
                    ]);
                }

                if (
                    $discount->usage_limit !== null &&
                    $discount->usage_count >=
                    $discount->usage_limit
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' =>
                            'This discount code has reached its usage limit.',
                    ]);
                }

                if (
                    $discount->usage_limit_per_client !== null
                ) {
                    $clientUsageCount =
                        DiscountUsage::query()
                            ->where(
                                'discount_code_id',
                                $discount->id
                            )
                            ->where(
                                'client_id',
                                $clientId
                            )
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
                        $subtotal *
                        ((float) $discount->value / 100);
                } else {
                    $discountAmount =
                        (float) $discount->value;
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
                    $discountAmount =
                        $subtotal;
                }

                $discountAmount =
                    round($discountAmount, 2);
            }

            /*
             * Referral Reward
             */
            if ($useReferralReward) {
                $referralRewardService =
                    app(ReferralRewardService::class);

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
             * Final Total
             */
            $totalAmount =
                $subtotal - $discountAmount;

            if ($totalAmount < 0) {
                $totalAmount = 0;
            }

            $totalAmount =
                round($totalAmount, 2);

            /*
             * Create Booking
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
             * Create Booking Services
             */
            foreach ($bookingServices as $bookingService) {
                BookingServiceModel::create([
                    'booking_id' => $booking->id,
                    ...$bookingService,
                ]);
            }

            /*
             * Record Generic Discount Usage
             */
            if (
                $discount &&
                $discountAmount > 0
            ) {
                DiscountUsage::create([
                    'discount_code_id' =>
                        $discount->id,

                    'client_id' =>
                        $clientId,

                    'booking_id' =>
                        $booking->id,

                    'discount_amount' =>
                        $discountAmount,
                ]);

                $discount->increment(
                    'usage_count'
                );
            }

            /*
             * Record Referral Reward Usage
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
             * Convert Booking Hold
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
                    'status' => 'converted',
                ]);

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
            ])) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'This booking cannot be cancelled.',
                ]);
            }

            $bookingStart =
                Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $booking->booking_date->format('Y-m-d')
                    . ' '
                    . $booking->start_time
                );

            $hoursUntilBooking =
                now()->diffInHours(
                    $bookingStart,
                    false
                );

            if ($hoursUntilBooking < 24) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'Booking can only be cancelled at least 24 hours before the appointment.',
                ]);
            }

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

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

            return $booking->fresh([
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
            ])) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'This booking cannot be rescheduled.',
                ]);
            }

            $bookingStart =
                Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $booking->booking_date->format('Y-m-d')
                    . ' '
                    . $booking->start_time
                );

            $hoursUntilBooking =
                now()->diffInHours(
                    $bookingStart,
                    false
                );

            if ($hoursUntilBooking < 24) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'Booking can only be rescheduled at least 24 hours before the appointment.',
                ]);
            }

            try {
                $newBookingStart =
                    Carbon::createFromFormat(
                        'H:i',
                        $newStartTime
                    );
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'booking' =>
                        "Invalid start time: {$newStartTime}.",
                ]);
            }

            if (
                $newBookingStart->format('H:i')
                !== $newStartTime
            ) {
                throw ValidationException::withMessages([
                    'booking' =>
                        "Invalid start time: {$newStartTime}.",
                ]);
            }

            $bookingServices =
                $booking->bookingServices()
                    ->orderBy('start_time')
                    ->get();

            if ($bookingServices->isEmpty()) {
                throw ValidationException::withMessages([
                    'booking' =>
                        'Booking has no services.',
                ]);
            }

            $oldBookingStart =
                Carbon::createFromFormat(
                    'H:i:s',
                    $booking->start_time
                );

            $offsetMinutes =
                $oldBookingStart->diffInMinutes(
                    $newBookingStart,
                    false
                );

            $availabilityService =
                app(
                    BookingAvailabilityService::class
                );

            $newBookingServices = [];

            foreach ($bookingServices as $bookingService) {
                $serviceStart =
                    Carbon::createFromFormat(
                        'H:i:s',
                        $bookingService->start_time
                    );

                $serviceEnd =
                    Carbon::createFromFormat(
                        'H:i:s',
                        $bookingService->end_time
                    );

                $newServiceStart =
                    $serviceStart
                        ->copy()
                        ->addMinutes($offsetMinutes);

                $newServiceEnd =
                    $serviceEnd
                        ->copy()
                        ->addMinutes($offsetMinutes);

                $isAvailable =
                    $availabilityService->isTimeAvailable(
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
                    'id' => $bookingService->id,
                    'start_time' =>
                        $newServiceStart->format('H:i:s'),
                    'end_time' =>
                        $newServiceEnd->format('H:i:s'),
                ];
            }

            $newBookingEnd =
                Carbon::createFromFormat(
                    'H:i:s',
                    $booking->end_time
                )->addMinutes($offsetMinutes);

            $booking->update([
                'booking_date' => $newDate,
                'start_time' =>
                    $newBookingStart->format('H:i:s'),
                'end_time' =>
                    $newBookingEnd->format('H:i:s'),
            ]);

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

            return $booking->fresh([
                'bookingServices.service',
                'bookingServices.staff',
                'payments',
            ]);
        });
    }
}
