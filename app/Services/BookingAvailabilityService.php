<?php

namespace App\Services;

use App\Models\BookingService as BookingServiceModel;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    public function getAvailableSlots(
        int $staffId,
        string $date,
        int $duration,
        int $interval = 30,
        ?int $ignoreBookingId = null
    ): Collection {
        $staff = Staff::with([
            'schedules',
            'breaks',
            'leaves',
        ])->findOrFail($staffId);

        $dateObject = Carbon::createFromFormat('Y-m-d', $date);

        $dayOfWeek = $dateObject->dayOfWeek;

        if (!$staff->is_active) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Schedules
        |--------------------------------------------------------------------------
        */

        $schedules = $staff->schedules
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->sortBy('start_time')
            ->values();

        if ($schedules->isEmpty()) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Full Day Leave
        |--------------------------------------------------------------------------
        */

        $fullDayLeave = $staff->leaves
            ->where('is_active', true)
            ->filter(function ($leave) use ($date) {
                return $leave->leave_date
                    && $leave->leave_date->format('Y-m-d') === $date
                    && is_null($leave->start_time)
                    && is_null($leave->end_time);
            })
            ->isNotEmpty();

        if ($fullDayLeave) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Partial Leaves
        |--------------------------------------------------------------------------
        */

        $leaves = $staff->leaves
            ->where('is_active', true)
            ->filter(function ($leave) use ($date) {
                return $leave->leave_date
                    && $leave->leave_date->format('Y-m-d') === $date
                    && !is_null($leave->start_time)
                    && !is_null($leave->end_time);
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Breaks
        |--------------------------------------------------------------------------
        */

        $breaks = $staff->breaks
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Existing Bookings
        |--------------------------------------------------------------------------
        */

        $existingBookings = BookingServiceModel::query()
            ->where('staff_id', $staffId)
            ->whereHas('booking', function ($query) use ($date, $ignoreBookingId) {

                $query
                    ->whereDate('booking_date', $date)
                    ->whereNotIn('status', [
                        'cancelled',
                        'rejected',
                        'no_show',
                    ]);

                if ($ignoreBookingId) {
                    $query->where('id', '!=', $ignoreBookingId);
                }
            })
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Active Holds
        |--------------------------------------------------------------------------
        */

        $activeHolds = $staff->bookingHolds()
            ->whereDate('hold_date', $date)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Generate Slots
        |--------------------------------------------------------------------------
        */

        $availableSlots = collect();

        foreach ($schedules as $schedule) {

            $scheduleStart = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $date . ' ' . $schedule->start_time
            );

            $scheduleEnd = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $date . ' ' . $schedule->end_time
            );

            $current = $scheduleStart->copy();

            while (
            $current->copy()->addMinutes($duration)->lte($scheduleEnd)
            ) {
                $slotStart = $current->copy();

                $slotEnd = $current
                    ->copy()
                    ->addMinutes($duration);

                /*
                |--------------------------------------------------------------------------
                | Break
                |--------------------------------------------------------------------------
                */

                $overlapsBreak = $breaks->contains(
                    function ($break) use ($date, $slotStart, $slotEnd) {

                        $breakStart = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $break->start_time
                        );

                        $breakEnd = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $break->end_time
                        );

                        return $slotStart->lt($breakEnd)
                            && $slotEnd->gt($breakStart);
                    }
                );

                if ($overlapsBreak) {
                    $current->addMinutes($interval);
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Leave
                |--------------------------------------------------------------------------
                */

                $overlapsLeave = $leaves->contains(
                    function ($leave) use ($date, $slotStart, $slotEnd) {

                        $leaveStart = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $leave->start_time
                        );

                        $leaveEnd = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $leave->end_time
                        );

                        return $slotStart->lt($leaveEnd)
                            && $slotEnd->gt($leaveStart);
                    }
                );

                if ($overlapsLeave) {
                    $current->addMinutes($interval);
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Existing Booking
                |--------------------------------------------------------------------------
                */

                $overlapsBooking = $existingBookings->contains(
                    function (BookingServiceModel $bookingService) use (
                        $date,
                        $slotStart,
                        $slotEnd
                    ) {

                        $bookingStart = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $bookingService->start_time
                        );

                        $bookingEnd = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $bookingService->end_time
                        );

                        return $slotStart->lt($bookingEnd)
                            && $slotEnd->gt($bookingStart);
                    }
                );

                if ($overlapsBooking) {
                    $current->addMinutes($interval);
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Active Hold
                |--------------------------------------------------------------------------
                */

                $overlapsHold = $activeHolds->contains(
                    function ($hold) use (
                        $date,
                        $slotStart,
                        $slotEnd
                    ) {

                        $holdStart = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $hold->start_time
                        );

                        $holdEnd = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $hold->end_time
                        );

                        return $slotStart->lt($holdEnd)
                            && $slotEnd->gt($holdStart);
                    }
                );

                if ($overlapsHold) {
                    $current->addMinutes($interval);
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Available
                |--------------------------------------------------------------------------
                */

                $availableSlots->push([
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'duration' => $duration,
                ]);

                $current->addMinutes($interval);
            }
        }

        return $availableSlots
            ->unique('start_time')
            ->values();
    }

    public function isTimeAvailable(
        int $staffId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreBookingId = null
    ): bool {
        $staff = Staff::with([
            'schedules',
            'breaks',
            'leaves',
        ])->findOrFail($staffId);

        if (!$staff->is_active) {
            return false;
        }

        $dateObject = Carbon::createFromFormat(
            'Y-m-d',
            $date
        );

        $dayOfWeek = $dateObject->dayOfWeek;

        $requestedStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date . ' ' . $startTime
        );

        $requestedEnd = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date . ' ' . $endTime
        );

        /*
        |--------------------------------------------------------------------------
        | Must be inside at least one working schedule
        |--------------------------------------------------------------------------
        */

        $insideSchedule = $staff->schedules
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->contains(function ($schedule) use (
                $date,
                $requestedStart,
                $requestedEnd
            ) {
                $scheduleStart = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $schedule->start_time
                );

                $scheduleEnd = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $schedule->end_time
                );

                return $requestedStart->gte($scheduleStart)
                    && $requestedEnd->lte($scheduleEnd);
            });

        if (!$insideSchedule) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Full Day Leave
        |--------------------------------------------------------------------------
        */

        $fullDayLeave = $staff->leaves
            ->where('is_active', true)
            ->contains(function ($leave) use ($date) {
                return $leave->leave_date
                    && $leave->leave_date->format('Y-m-d') === $date
                    && is_null($leave->start_time)
                    && is_null($leave->end_time);
            });

        if ($fullDayLeave) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Partial Leaves
        |--------------------------------------------------------------------------
        */

        $partialLeave = $staff->leaves
            ->where('is_active', true)
            ->contains(function ($leave) use (
                $date,
                $requestedStart,
                $requestedEnd
            ) {
                if (
                    !$leave->leave_date ||
                    $leave->leave_date->format('Y-m-d') !== $date ||
                    is_null($leave->start_time) ||
                    is_null($leave->end_time)
                ) {
                    return false;
                }

                $leaveStart = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $leave->start_time
                );

                $leaveEnd = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $leave->end_time
                );

                return $requestedStart->lt($leaveEnd)
                    && $requestedEnd->gt($leaveStart);
            });

        if ($partialLeave) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Breaks
        |--------------------------------------------------------------------------
        */

        $overlapsBreak = $staff->breaks
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->contains(function ($break) use (
                $date,
                $requestedStart,
                $requestedEnd
            ) {
                $breakStart = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $break->start_time
                );

                $breakEnd = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $break->end_time
                );

                return $requestedStart->lt($breakEnd)
                    && $requestedEnd->gt($breakStart);
            });

        if ($overlapsBreak) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Bookings
        |--------------------------------------------------------------------------
        */

        $overlapsBooking = BookingServiceModel::query()
            ->where('staff_id', $staffId)
            ->whereHas('booking', function ($query) use (
                $date,
                $ignoreBookingId
            ) {
                $query
                    ->whereDate('booking_date', $date)
                    ->whereNotIn('status', [
                        'cancelled',
                        'rejected',
                        'no_show',
                    ]);

                if ($ignoreBookingId) {
                    $query->where('id', '!=', $ignoreBookingId);
                }
            })
            ->get()
            ->contains(function ($bookingService) use (
                $date,
                $requestedStart,
                $requestedEnd
            ) {
                $bookingStart = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $bookingService->start_time
                );

                $bookingEnd = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $bookingService->end_time
                );

                return $requestedStart->lt($bookingEnd)
                    && $requestedEnd->gt($bookingStart);
            });

        if ($overlapsBooking) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Active Booking Holds
        |--------------------------------------------------------------------------
        */

        $overlapsHold = $staff->bookingHolds()
            ->whereDate('hold_date', $date)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get()
            ->contains(function ($hold) use (
                $date,
                $requestedStart,
                $requestedEnd
            ) {
                $holdStart = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $hold->start_time
                );

                $holdEnd = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $date . ' ' . $hold->end_time
                );

                return $requestedStart->lt($holdEnd)
                    && $requestedEnd->gt($holdStart);
            });

        if ($overlapsHold) {
            return false;
        }

        return true;
    }
}

