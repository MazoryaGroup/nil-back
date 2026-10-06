<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use Carbon\Carbon;

class AccountingReportService
{
    /**
     * Financial summary between two dates.
     */
    public function summary(
        Carbon|string $from,
        Carbon|string $to
    ): array {

        $from = $from instanceof Carbon
            ? $from->copy()
            : Carbon::parse($from);

        $to = $to instanceof Carbon
            ? $to->copy()
            : Carbon::parse($to);

        $from->startOfDay();
        $to->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Income
        |--------------------------------------------------------------------------
        */

        $income = AccountingTransaction::query()
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | Expenses
        |--------------------------------------------------------------------------
        */

        $expense = AccountingTransaction::query()
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | Refunds
        |--------------------------------------------------------------------------
        */

        $refund = AccountingTransaction::query()
            ->where('type', 'refund')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | Net Revenue
        |--------------------------------------------------------------------------
        */

        $netRevenue =
            (float) $income
            - (float) $expense
            - (float) $refund;

        /*
        |--------------------------------------------------------------------------
        | Payment Methods
        |--------------------------------------------------------------------------
        */

        $paymentMethods = AccountingTransaction::query()
            ->selectRaw('payment_method, SUM(amount) as total')
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from, $to])
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($value) => (float) $value)
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [
            'from' => $from->toDateTimeString(),

            'to' => $to->toDateTimeString(),

            'income' => (float) $income,

            'expense' => (float) $expense,

            'refund' => (float) $refund,

            'net_revenue' => $netRevenue,

            'payment_methods' => [
                'online' => $paymentMethods['online'] ?? 0,
                'cash' => $paymentMethods['cash'] ?? 0,
                'pos' => $paymentMethods['pos'] ?? 0,
                'bank_transfer' => $paymentMethods['bank_transfer'] ?? 0,
                'other' => $paymentMethods['other'] ?? 0,
            ],
        ];
    }

    /**
     * Today's financial report.
     */
    public function today(): array
    {
        return $this->summary(
            now()->copy()->startOfDay(),
            now()->copy()->endOfDay()
        );
    }

    /**
     * Current month financial report.
     */
    public function currentMonth(): array
    {
        return $this->summary(
            now()->copy()->startOfMonth(),
            now()->copy()->endOfMonth()
        );
    }
    /**
     * Daily financial report between two dates.
     */
    public function dailyReport(
        Carbon|string $from,
        Carbon|string $to
    ): array {

        $from = $from instanceof Carbon
            ? $from->copy()
            : Carbon::parse($from);

        $to = $to instanceof Carbon
            ? $to->copy()
            : Carbon::parse($to);

        $from->startOfDay();
        $to->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Get Daily Transactions
        |--------------------------------------------------------------------------
        */

        $rows = AccountingTransaction::query()
            ->selectRaw("
            DATE(transaction_date) as report_date,

            SUM(
                CASE
                    WHEN type = 'income'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ) as income,

            SUM(
                CASE
                    WHEN type = 'expense'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ) as expense,

            SUM(
                CASE
                    WHEN type = 'refund'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ) as refund
        ")
            ->whereBetween(
                'transaction_date',
                [$from, $to]
            )
            ->groupByRaw('DATE(transaction_date)')
            ->orderByRaw('DATE(transaction_date)')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Format Result
        |--------------------------------------------------------------------------
        */

        return $rows
            ->map(function ($row) {

                $income = (float) $row->income;
                $expense = (float) $row->expense;
                $refund = (float) $row->refund;

                return [
                    'date' => $row->report_date,

                    'income' => $income,

                    'expense' => $expense,

                    'refund' => $refund,

                    'net_revenue' =>
                        $income
                        - $expense
                        - $refund,
                ];
            })
            ->values()
            ->toArray();
    }
    /**
     * Daily report for current month.
     */
    public function currentMonthDaily(): array
    {
        return $this->dailyReport(
            now()->copy()->startOfMonth(),
            now()->copy()->endOfMonth()
        );
    }
    /**
     * Staff performance report between two dates.
     */
    public function staffReport(
        Carbon|string $from,
        Carbon|string $to,
        ?int $staffId = null,
        ?int $serviceId = null
    ): array {

        $from = $from instanceof Carbon
            ? $from->copy()
            : Carbon::parse($from);

        $to = $to instanceof Carbon
            ? $to->copy()
            : Carbon::parse($to);

        $from->startOfDay();
        $to->endOfDay();

        $query = \App\Models\BookingService::query()
            ->join(
                'bookings',
                'booking_services.booking_id',
                '=',
                'bookings.id'
            )
            ->join(
                'staff',
                'booking_services.staff_id',
                '=',
                'staff.id'
            )
            ->whereBetween(
                'bookings.booking_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            )
            ->whereNotIn(
                'bookings.status',
                ['cancelled']
            );

        if ($staffId !== null) {
            $query->where(
                'booking_services.staff_id',
                $staffId
            );
        }

        if ($serviceId !== null) {
            $query->where(
                'booking_services.service_id',
                $serviceId
            );
        }

        $rows = $query
            ->selectRaw('
            staff.id as staff_id,
            staff.name as staff_name,
            COUNT(DISTINCT bookings.id) as bookings_count,
            COUNT(booking_services.id) as services_count,
            COALESCE(SUM(booking_services.price), 0) as services_amount,
            COALESCE(SUM(booking_services.duration), 0) as total_minutes
        ')
            ->groupBy(
                'staff.id',
                'staff.name'
            )
            ->orderByDesc('services_amount')
            ->get();

        return $rows
            ->map(function ($row) {

                $totalMinutes = (int) $row->total_minutes;

                return [
                    'staff_id' => (int) $row->staff_id,

                    'staff_name' => $row->staff_name,

                    'bookings_count' =>
                        (int) $row->bookings_count,

                    'services_count' =>
                        (int) $row->services_count,

                    'services_amount' =>
                        (float) $row->services_amount,

                    'total_minutes' =>
                        $totalMinutes,

                    'total_hours' =>
                        round($totalMinutes / 60, 2),
                ];
            })
            ->values()
            ->toArray();
    }
    /**
     * Staff report for current month.
     */
    public function currentMonthStaff(): array
    {
        return $this->staffReport(
            now()->copy()->startOfMonth(),
            now()->copy()->endOfMonth()
        );
    }
    /**
     * Services performance report between two dates.
     */
    /**
     * Services performance report between two dates.
     *
     * Optional filters:
     * - Staff
     * - Service
     */
    public function serviceReport(
        Carbon|string $from,
        Carbon|string $to,
        ?int $staffId = null,
        ?int $serviceId = null
    ): array {

        $from = $from instanceof Carbon
            ? $from->copy()
            : Carbon::parse($from);

        $to = $to instanceof Carbon
            ? $to->copy()
            : Carbon::parse($to);

        $from->startOfDay();
        $to->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Base Query
        |--------------------------------------------------------------------------
        */

        $query = \App\Models\BookingService::query()
            ->join(
                'bookings',
                'booking_services.booking_id',
                '=',
                'bookings.id'
            )
            ->join(
                'services',
                'booking_services.service_id',
                '=',
                'services.id'
            )
            ->whereBetween(
                'bookings.booking_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            )
            ->whereNotIn(
                'bookings.status',
                ['cancelled']
            );

        /*
        |--------------------------------------------------------------------------
        | Staff Filter
        |--------------------------------------------------------------------------
        */

        if ($staffId !== null) {
            $query->where(
                'booking_services.staff_id',
                $staffId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Service Filter
        |--------------------------------------------------------------------------
        */

        if ($serviceId !== null) {
            $query->where(
                'booking_services.service_id',
                $serviceId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Get Services Report
        |--------------------------------------------------------------------------
        */

        $rows = $query
            ->selectRaw('
            services.id as service_id,
            services.name as service_name,

            COUNT(
                DISTINCT bookings.id
            ) as bookings_count,

            COUNT(
                booking_services.id
            ) as services_count,

            COALESCE(
                SUM(booking_services.price),
                0
            ) as services_amount,

            COALESCE(
                SUM(booking_services.duration),
                0
            ) as total_minutes
        ')
            ->groupBy(
                'services.id',
                'services.name'
            )
            ->orderByDesc(
                'services_amount'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Format Result
        |--------------------------------------------------------------------------
        */

        return $rows
            ->map(function ($row) {

                $totalMinutes =
                    (int) $row->total_minutes;

                return [
                    'service_id' =>
                        (int) $row->service_id,

                    'service_name' =>
                        $row->service_name,

                    'bookings_count' =>
                        (int) $row->bookings_count,

                    'services_count' =>
                        (int) $row->services_count,

                    'services_amount' =>
                        (float) $row->services_amount,

                    'total_minutes' =>
                        $totalMinutes,

                    'total_hours' =>
                        round(
                            $totalMinutes / 60,
                            2
                        ),
                ];
            })
            ->values()
            ->toArray();
    }
    /**
     * Services report for current month.
     */
    public function currentMonthServices(): array
    {
        return $this->serviceReport(
            now()->copy()->startOfMonth(),
            now()->copy()->endOfMonth()
        );
    }
    /**
     * Customers with outstanding booking balances.
     */
    public function outstandingCustomers(): array
    {
        $bookings = \App\Models\Booking::query()
            ->with('client')
            ->whereNotIn('status', ['cancelled'])
            ->where('total_amount', '>', 0)
            ->orderByDesc('booking_date')
            ->get();

        $customers = [];

        foreach ($bookings as $booking) {

            /*
            |--------------------------------------------------------------------------
            | Paid Amount
            |--------------------------------------------------------------------------
            */

            $paidAmount = \App\Models\Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'paid')
                ->whereIn('type', ['deposit', 'remaining'])
                ->sum('amount');

            /*
            |--------------------------------------------------------------------------
            | Refunded Amount
            |--------------------------------------------------------------------------
            */

            $refundedAmount = \App\Models\Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'refund')
                ->whereIn('status', ['paid', 'refunded'])
                ->sum('amount');

            /*
            |--------------------------------------------------------------------------
            | Net Paid
            |--------------------------------------------------------------------------
            */

            $netPaidAmount = max(
                0,
                (float) $paidAmount - (float) $refundedAmount
            );

            /*
            |--------------------------------------------------------------------------
            | Outstanding
            |--------------------------------------------------------------------------
            */

            $outstandingAmount = max(
                0,
                (float) $booking->total_amount - $netPaidAmount
            );

            if ($outstandingAmount <= 0) {
                continue;
            }

            $clientId = $booking->client_id;

            /*
            |--------------------------------------------------------------------------
            | Create Customer
            |--------------------------------------------------------------------------
            */

            if (!isset($customers[$clientId])) {

                $customers[$clientId] = [
                    'client_id' => $clientId,

                    'client_name' =>
                        $booking->client?->name,

                    'client_phone' =>
                        $booking->client?->phone,

                    'bookings_count' => 0,

                    'total_amount' => 0,

                    'paid_amount' => 0,

                    'refund_amount' => 0,

                    'net_paid_amount' => 0,

                    'outstanding_amount' => 0,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Add Booking Values
            |--------------------------------------------------------------------------
            */

            $customers[$clientId]['bookings_count']++;

            $customers[$clientId]['total_amount'] +=
                (float) $booking->total_amount;

            $customers[$clientId]['paid_amount'] +=
                (float) $paidAmount;

            $customers[$clientId]['refund_amount'] +=
                (float) $refundedAmount;

            $customers[$clientId]['net_paid_amount'] +=
                $netPaidAmount;

            $customers[$clientId]['outstanding_amount'] +=
                $outstandingAmount;
        }

        /*
        |--------------------------------------------------------------------------
        | Sort By Highest Outstanding
        |--------------------------------------------------------------------------
        */

        $customers = array_values($customers);

        usort(
            $customers,
            fn ($a, $b) =>
                $b['outstanding_amount']
                <=>
                $a['outstanding_amount']
        );

        return $customers;
    }
    /**
     * Staff attendance / schedule report between two dates.
     */
    public function staffScheduleReport(
        int $staffId,
        Carbon|string $from,
        Carbon|string $to
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Dates
        |--------------------------------------------------------------------------
        */

        $from = $from instanceof Carbon
            ? $from->copy()
            : Carbon::parse($from);

        $to = $to instanceof Carbon
            ? $to->copy()
            : Carbon::parse($to);

        $from->startOfDay();
        $to->endOfDay();

        $today = today();

        /*
        |--------------------------------------------------------------------------
        | Helper: Merge Intervals
        |--------------------------------------------------------------------------
        */

        $mergeIntervals = function (array $intervals): array {

            if (empty($intervals)) {
                return [];
            }

            usort($intervals, function ($a, $b) {
                return $a['start']->timestamp <=> $b['start']->timestamp;
            });

            $merged = [];

            foreach ($intervals as $interval) {

                if (
                    !isset($interval['start'], $interval['end']) ||
                    $interval['end']->lte($interval['start'])
                ) {
                    continue;
                }

                if (empty($merged)) {

                    $merged[] = [
                        'start' => $interval['start']->copy(),
                        'end' => $interval['end']->copy(),
                    ];

                    continue;
                }

                $lastIndex = count($merged) - 1;

                if (
                    $interval['start']->lte(
                        $merged[$lastIndex]['end']
                    )
                ) {

                    if (
                        $interval['end']->gt(
                            $merged[$lastIndex]['end']
                        )
                    ) {
                        $merged[$lastIndex]['end'] =
                            $interval['end']->copy();
                    }

                } else {

                    $merged[] = [
                        'start' => $interval['start']->copy(),
                        'end' => $interval['end']->copy(),
                    ];
                }
            }

            return $merged;
        };

        /*
        |--------------------------------------------------------------------------
        | Helper: Total Minutes
        |--------------------------------------------------------------------------
        */

        $intervalMinutes = function (array $intervals): int {

            $minutes = 0;

            foreach ($intervals as $interval) {

                if (
                    isset($interval['start'], $interval['end']) &&
                    $interval['end']->gt($interval['start'])
                ) {
                    $minutes +=
                        $interval['start']->diffInMinutes(
                            $interval['end']
                        );
                }
            }

            return $minutes;
        };

        /*
        |--------------------------------------------------------------------------
        | Helper: Intersection Between Two Interval Sets
        |--------------------------------------------------------------------------
        */

        $intersectIntervals = function (
            array $sourceIntervals,
            array $limitIntervals
        ) use ($mergeIntervals): array {

            $result = [];

            foreach ($sourceIntervals as $source) {

                foreach ($limitIntervals as $limit) {

                    $start = $source['start']->gt($limit['start'])
                        ? $source['start']->copy()
                        : $limit['start']->copy();

                    $end = $source['end']->lt($limit['end'])
                        ? $source['end']->copy()
                        : $limit['end']->copy();

                    if ($end->gt($start)) {

                        $result[] = [
                            'start' => $start,
                            'end' => $end,
                        ];
                    }
                }
            }

            return $mergeIntervals($result);
        };

        /*
        |--------------------------------------------------------------------------
        | Helper: Subtract Intervals
        |--------------------------------------------------------------------------
        |
        | مثال:
        |
        | Schedule: 09:00 - 13:00
        | Break:    11:00 - 12:00
        |
        | Result:
        | 09:00 - 11:00
        | 12:00 - 13:00
        |
        */

        $subtractIntervals = function (
            array $baseIntervals,
            array $removeIntervals
        ) use ($mergeIntervals): array {

            $baseIntervals = $mergeIntervals($baseIntervals);
            $removeIntervals = $mergeIntervals($removeIntervals);

            $result = [];

            foreach ($baseIntervals as $base) {

                $segments = [
                    [
                        'start' => $base['start']->copy(),
                        'end' => $base['end']->copy(),
                    ]
                ];

                foreach ($removeIntervals as $remove) {

                    $newSegments = [];

                    foreach ($segments as $segment) {

                        /*
                        | No overlap
                        */
                        if (
                            $remove['end']->lte($segment['start']) ||
                            $remove['start']->gte($segment['end'])
                        ) {
                            $newSegments[] = $segment;
                            continue;
                        }

                        /*
                        | Left side remains
                        */
                        if ($remove['start']->gt($segment['start'])) {

                            $leftEnd = $remove['start']->lt($segment['end'])
                                ? $remove['start']->copy()
                                : $segment['end']->copy();

                            if ($leftEnd->gt($segment['start'])) {

                                $newSegments[] = [
                                    'start' => $segment['start']->copy(),
                                    'end' => $leftEnd,
                                ];
                            }
                        }

                        /*
                        | Right side remains
                        */
                        if ($remove['end']->lt($segment['end'])) {

                            $rightStart = $remove['end']->gt($segment['start'])
                                ? $remove['end']->copy()
                                : $segment['start']->copy();

                            if ($segment['end']->gt($rightStart)) {

                                $newSegments[] = [
                                    'start' => $rightStart,
                                    'end' => $segment['end']->copy(),
                                ];
                            }
                        }
                    }

                    $segments = $newSegments;

                    if (empty($segments)) {
                        break;
                    }
                }

                foreach ($segments as $segment) {
                    $result[] = $segment;
                }
            }

            return $mergeIntervals($result);
        };

        /*
        |--------------------------------------------------------------------------
        | Helper: Interval Details
        |--------------------------------------------------------------------------
        */

        $intervalDetails = function (array $intervals): array {

            return collect($intervals)
                ->map(function ($interval) {

                    $minutes =
                        $interval['start']->diffInMinutes(
                            $interval['end']
                        );

                    return [
                        'start_time' =>
                            $interval['start']->format('H:i:s'),

                        'end_time' =>
                            $interval['end']->format('H:i:s'),

                        'minutes' =>
                            $minutes,

                        'hours' =>
                            round($minutes / 60, 2),
                    ];
                })
                ->values()
                ->toArray();
        };

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        $staff = \App\Models\Staff::query()
            ->findOrFail($staffId);

        /*
        |--------------------------------------------------------------------------
        | Weekly Schedules
        |--------------------------------------------------------------------------
        */

        $schedules = \App\Models\StaffSchedule::query()
            ->where('staff_id', $staffId)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Weekly Breaks
        |--------------------------------------------------------------------------
        */

        $breaks = \App\Models\StaffBreak::query()
            ->where('staff_id', $staffId)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Leaves
        |--------------------------------------------------------------------------
        */

        $leaves = \App\Models\StaffLeave::query()
            ->where('staff_id', $staffId)
            ->whereBetween(
                'leave_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            )
            ->get()
            ->groupBy(function ($leave) {

                return Carbon::parse(
                    $leave->leave_date
                )->toDateString();
            });

        /*
        |--------------------------------------------------------------------------
        | Booking Services
        |--------------------------------------------------------------------------
        */

        $bookingServices = \App\Models\BookingService::query()
            ->join(
                'bookings',
                'booking_services.booking_id',
                '=',
                'bookings.id'
            )
            ->where(
                'booking_services.staff_id',
                $staffId
            )
            ->whereBetween(
                'bookings.booking_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            )
            ->whereNotIn(
                'bookings.status',
                ['cancelled']
            )
            ->select([
                'booking_services.id',
                'booking_services.booking_id',
                'booking_services.start_time',
                'booking_services.end_time',
                'booking_services.duration',
                'bookings.booking_date',
            ])
            ->orderBy('bookings.booking_date')
            ->orderBy('booking_services.start_time')
            ->get()
            ->groupBy(function ($row) {

                return Carbon::parse(
                    $row->booking_date
                )->toDateString();
            });

        /*
        |--------------------------------------------------------------------------
        | Calendar
        |--------------------------------------------------------------------------
        */

        $days = [];

        $scheduledDaysCount = 0;
        $workedDaysCount = 0;
        $leaveDaysCount = 0;
        $noBookingDaysCount = 0;
        $futureScheduledDaysCount = 0;

        $totalScheduledMinutes = 0;
        $totalBreakMinutes = 0;
        $totalLeaveMinutes = 0;

        /*
        | Worked inside actual available schedule.
        */
        $totalWorkedMinutes = 0;

        /*
        | Real occupied time regardless of schedule.
        */
        $totalRawWorkedMinutes = 0;

        /*
        | Sum of service durations.
        */
        $totalServiceWorkloadMinutes = 0;

        $current = $from->copy()->startOfDay();
        $lastDate = $to->copy()->startOfDay();

        while ($current->lte($lastDate)) {

            $date = $current->toDateString();

            $dayOfWeek = $current->dayOfWeek;

            $isFuture = $current->gt($today);

            /*
            |--------------------------------------------------------------------------
            | Schedule Intervals
            |--------------------------------------------------------------------------
            */

            $daySchedules = $schedules
                ->where('day_of_week', $dayOfWeek)
                ->values();

            $scheduleIntervals = [];

            foreach ($daySchedules as $schedule) {

                $start = Carbon::parse(
                    $date . ' ' . $schedule->start_time
                );

                $end = Carbon::parse(
                    $date . ' ' . $schedule->end_time
                );

                if ($end->gt($start)) {

                    $scheduleIntervals[] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }

            /*
            | اگر چند Schedule هم overlap داشته باشند،
            | دوبار محاسبه نمی‌شوند.
            */
            $scheduleIntervals =
                $mergeIntervals($scheduleIntervals);

            $dayScheduledMinutes =
                $intervalMinutes($scheduleIntervals);

            $scheduleDetails =
                $intervalDetails($scheduleIntervals);

            $isScheduled =
                !empty($scheduleIntervals);

            /*
            |--------------------------------------------------------------------------
            | Break Intervals
            |--------------------------------------------------------------------------
            */

            $dayBreakRows = $breaks
                ->where('day_of_week', $dayOfWeek)
                ->values();

            $rawBreakIntervals = [];

            foreach ($dayBreakRows as $break) {

                $start = Carbon::parse(
                    $date . ' ' . $break->start_time
                );

                $end = Carbon::parse(
                    $date . ' ' . $break->end_time
                );

                if ($end->gt($start)) {

                    $rawBreakIntervals[] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }

            /*
            | فقط بخش Break که داخل Schedule است.
            */
            $breakIntervals =
                $intersectIntervals(
                    $rawBreakIntervals,
                    $scheduleIntervals
                );

            $dayBreakMinutes =
                $intervalMinutes($breakIntervals);

            $breakDetails =
                $intervalDetails($breakIntervals);

            /*
            |--------------------------------------------------------------------------
            | Leave Intervals
            |--------------------------------------------------------------------------
            */

            $dayLeaves = $leaves->get(
                $date,
                collect()
            );

            $rawLeaveIntervals = [];

            foreach ($dayLeaves as $leave) {

                $start = Carbon::parse(
                    $date . ' ' . $leave->start_time
                );

                $end = Carbon::parse(
                    $date . ' ' . $leave->end_time
                );

                if ($end->gt($start)) {

                    $rawLeaveIntervals[] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }

            /*
            | فقط مرخصی داخل Schedule.
            */
            $leaveIntervals =
                $intersectIntervals(
                    $rawLeaveIntervals,
                    $scheduleIntervals
                );

            $dayLeaveMinutes =
                $intervalMinutes($leaveIntervals);

            $leaveDetails =
                $intervalDetails($leaveIntervals);

            /*
            | is_leave فقط زمانی true است که واقعاً
            | بخشی از ساعت کاری را پوشش داده باشد.
            */
            $isLeave =
                $dayLeaveMinutes > 0;

            /*
            |--------------------------------------------------------------------------
            | Unavailable Intervals
            |--------------------------------------------------------------------------
            |
            | Break و Leave ابتدا با هم Merge می‌شوند تا اگر overlap داشتند
            | دوبار از Schedule کم نشوند.
            |
            */

            $unavailableIntervals =
                $mergeIntervals(
                    array_merge(
                        $breakIntervals,
                        $leaveIntervals
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | Available Intervals
            |--------------------------------------------------------------------------
            */

            $availableIntervals =
                $subtractIntervals(
                    $scheduleIntervals,
                    $unavailableIntervals
                );

            $dayAvailableMinutes =
                $intervalMinutes($availableIntervals);

            /*
            |--------------------------------------------------------------------------
            | Booking Services
            |--------------------------------------------------------------------------
            */

            $dayBookingServices =
                $bookingServices->get(
                    $date,
                    collect()
                );

            $isWorked =
                $dayBookingServices->isNotEmpty();

            /*
            |--------------------------------------------------------------------------
            | Service Workload
            |--------------------------------------------------------------------------
            |
            | مجموع مدت سرویس‌ها.
            | Overlap حذف نمی‌شود.
            |
            */

            $dayServiceWorkloadMinutes =
                (int) $dayBookingServices
                    ->sum('duration');

            /*
            |--------------------------------------------------------------------------
            | Booking Intervals
            |--------------------------------------------------------------------------
            */

            $bookingIntervals = [];

            foreach ($dayBookingServices as $service) {

                if (
                    empty($service->start_time) ||
                    empty($service->end_time)
                ) {
                    continue;
                }

                $start = Carbon::parse(
                    $date . ' ' . $service->start_time
                );

                $end = Carbon::parse(
                    $date . ' ' . $service->end_time
                );

                if ($end->gt($start)) {

                    $bookingIntervals[] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }

            /*
            | Merge overlapping bookings.
            */
            $mergedBookingIntervals =
                $mergeIntervals($bookingIntervals);

            /*
            |--------------------------------------------------------------------------
            | Raw Worked Time
            |--------------------------------------------------------------------------
            |
            | زمان واقعی اشغال پرسنل بدون توجه به Schedule.
            |
            */

            $dayRawWorkedMinutes =
                $intervalMinutes(
                    $mergedBookingIntervals
                );

            /*
            |--------------------------------------------------------------------------
            | Worked Inside Schedule
            |--------------------------------------------------------------------------
            |
            | فقط بخشی از Booking که داخل زمان قابل کار واقعی است:
            |
            | Schedule
            | - Break
            | - Leave
            |
            */

            $workedIntervals =
                $intersectIntervals(
                    $mergedBookingIntervals,
                    $availableIntervals
                );

            $dayWorkedMinutes =
                $intervalMinutes(
                    $workedIntervals
                );

            /*
            |--------------------------------------------------------------------------
            | Outside Schedule Worked
            |--------------------------------------------------------------------------
            |
            | برای گزارش مدیریتی مفید است.
            | نشان می‌دهد چند دقیقه سرویس خارج از زمان قابل کار ثبت شده.
            |
            */

            $dayOutsideScheduleWorkedMinutes =
                max(
                    0,
                    $dayRawWorkedMinutes
                    - $dayWorkedMinutes
                );

            /*
            |--------------------------------------------------------------------------
            | Empty Time
            |--------------------------------------------------------------------------
            */

            $dayEmptyMinutes =
                max(
                    0,
                    $dayAvailableMinutes
                    - $dayWorkedMinutes
                );

            /*
            |--------------------------------------------------------------------------
            | Utilization
            |--------------------------------------------------------------------------
            */

            $dayUtilization =
                $dayAvailableMinutes > 0
                    ? round(
                    (
                        $dayWorkedMinutes /
                        $dayAvailableMinutes
                    ) * 100,
                    2
                )
                    : 0;

            /*
            |--------------------------------------------------------------------------
            | Counters
            |--------------------------------------------------------------------------
            */

            if ($isScheduled) {

                if ($isFuture) {

                    $futureScheduledDaysCount++;

                } else {

                    $scheduledDaysCount++;
                }
            }

            if (
                !$isFuture &&
                $isWorked
            ) {
                $workedDaysCount++;
            }

            if (
                !$isFuture &&
                $isLeave
            ) {
                $leaveDaysCount++;
            }

            if (
                !$isFuture &&
                $isScheduled &&
                !$isWorked &&
                !$isLeave
            ) {
                $noBookingDaysCount++;
            }

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $status = 'off';

            if ($isFuture) {

                $status = $isScheduled
                    ? 'scheduled'
                    : 'off';

            } elseif (
                $isWorked &&
                $isLeave
            ) {

                $status = 'worked_and_leave';

            } elseif ($isWorked) {

                $status = 'worked';

            } elseif ($isLeave) {

                $status = 'leave';

            } elseif ($isScheduled) {

                $status = 'no_booking';
            }

            /*
            |--------------------------------------------------------------------------
            | Totals
            |--------------------------------------------------------------------------
            */

            if (!$isFuture) {

                $totalScheduledMinutes +=
                    $dayScheduledMinutes;

                $totalBreakMinutes +=
                    $dayBreakMinutes;

                $totalLeaveMinutes +=
                    $dayLeaveMinutes;

                $totalWorkedMinutes +=
                    $dayWorkedMinutes;

                $totalRawWorkedMinutes +=
                    $dayRawWorkedMinutes;

                $totalServiceWorkloadMinutes +=
                    $dayServiceWorkloadMinutes;
            }

            /*
            |--------------------------------------------------------------------------
            | Day Result
            |--------------------------------------------------------------------------
            */

            $days[] = [

                'date' =>
                    $date,

                'day_of_week' =>
                    $dayOfWeek,

                'day_name' =>
                    $current->format('l'),

                'is_future' =>
                    $isFuture,

                'is_scheduled' =>
                    $isScheduled,

                'is_worked' =>
                    $isWorked,

                'is_leave' =>
                    $isLeave,

                'status' =>
                    $status,

                /*
                |--------------------------------------------------------------------------
                | Schedule
                |--------------------------------------------------------------------------
                */

                'schedules' =>
                    $scheduleDetails,

                'scheduled_minutes' =>
                    $dayScheduledMinutes,

                'scheduled_hours' =>
                    round(
                        $dayScheduledMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Break
                |--------------------------------------------------------------------------
                */

                'break_minutes' =>
                    $dayBreakMinutes,

                'break_hours' =>
                    round(
                        $dayBreakMinutes / 60,
                        2
                    ),

                'breaks' =>
                    $breakDetails,

                /*
                |--------------------------------------------------------------------------
                | Leave
                |--------------------------------------------------------------------------
                */

                'leave_minutes' =>
                    $dayLeaveMinutes,

                'leave_hours' =>
                    round(
                        $dayLeaveMinutes / 60,
                        2
                    ),

                'leaves' =>
                    $leaveDetails,

                /*
                |--------------------------------------------------------------------------
                | Available
                |--------------------------------------------------------------------------
                */

                'available_minutes' =>
                    $dayAvailableMinutes,

                'available_hours' =>
                    round(
                        $dayAvailableMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Worked Inside Available Schedule
                |--------------------------------------------------------------------------
                */

                'worked_minutes' =>
                    $dayWorkedMinutes,

                'worked_hours' =>
                    round(
                        $dayWorkedMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Raw Real Occupied
                |--------------------------------------------------------------------------
                */

                'raw_worked_minutes' =>
                    $dayRawWorkedMinutes,

                'raw_worked_hours' =>
                    round(
                        $dayRawWorkedMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Work Outside Schedule / Break / Leave
                |--------------------------------------------------------------------------
                */

                'outside_schedule_worked_minutes' =>
                    $dayOutsideScheduleWorkedMinutes,

                'outside_schedule_worked_hours' =>
                    round(
                        $dayOutsideScheduleWorkedMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Service Workload
                |--------------------------------------------------------------------------
                */

                'service_workload_minutes' =>
                    $dayServiceWorkloadMinutes,

                'service_workload_hours' =>
                    round(
                        $dayServiceWorkloadMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Intervals
                |--------------------------------------------------------------------------
                */

                'worked_intervals' =>
                    $intervalDetails(
                        $workedIntervals
                    ),

                'raw_worked_intervals' =>
                    $intervalDetails(
                        $mergedBookingIntervals
                    ),

                'available_intervals' =>
                    $intervalDetails(
                        $availableIntervals
                    ),

                /*
                |--------------------------------------------------------------------------
                | Empty
                |--------------------------------------------------------------------------
                */

                'empty_minutes' =>
                    $dayEmptyMinutes,

                'empty_hours' =>
                    round(
                        $dayEmptyMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Utilization
                |--------------------------------------------------------------------------
                */

                'utilization_percent' =>
                    $dayUtilization,
            ];

            $current->addDay();
        }

        /*
        |--------------------------------------------------------------------------
        | Final Available Time
        |--------------------------------------------------------------------------
        |
        | نکته:
        | Break و Leave ممکن است overlap داشته باشند.
        | بنابراین available را از مجموع daily available می‌گیریم،
        | نه scheduled - break - leave به صورت جداگانه.
        |
        */

        $pastDays = collect($days)
            ->filter(function ($day) use ($today) {

                return Carbon::parse(
                    $day['date']
                )->lte($today);
            });

        $totalAvailableMinutes =
            (int) $pastDays->sum(
                'available_minutes'
            );

        /*
        |--------------------------------------------------------------------------
        | Empty Time
        |--------------------------------------------------------------------------
        */

        $totalEmptyMinutes =
            (int) $pastDays->sum(
                'empty_minutes'
            );

        /*
        |--------------------------------------------------------------------------
        | Outside Schedule Worked
        |--------------------------------------------------------------------------
        */

        $totalOutsideScheduleWorkedMinutes =
            (int) $pastDays->sum(
                'outside_schedule_worked_minutes'
            );

        /*
        |--------------------------------------------------------------------------
        | Utilization
        |--------------------------------------------------------------------------
        */

        $utilizationPercent =
            $totalAvailableMinutes > 0
                ? round(
                (
                    $totalWorkedMinutes /
                    $totalAvailableMinutes
                ) * 100,
                2
            )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [

            'staff' => [

                'id' =>
                    (int) $staff->id,

                'name' =>
                    $staff->name,
            ],

            'period' => [

                'from' =>
                    $from->toDateString(),

                'to' =>
                    $to->toDateString(),
            ],

            'summary' => [

                'scheduled_days_count' =>
                    $scheduledDaysCount,

                'worked_days_count' =>
                    $workedDaysCount,

                'leave_days_count' =>
                    $leaveDaysCount,

                'no_booking_days_count' =>
                    $noBookingDaysCount,

                'future_scheduled_days_count' =>
                    $futureScheduledDaysCount,

                /*
                |--------------------------------------------------------------------------
                | Schedule
                |--------------------------------------------------------------------------
                */

                'scheduled_minutes' =>
                    $totalScheduledMinutes,

                'scheduled_hours' =>
                    round(
                        $totalScheduledMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Break
                |--------------------------------------------------------------------------
                */

                'break_minutes' =>
                    $totalBreakMinutes,

                'break_hours' =>
                    round(
                        $totalBreakMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Leave
                |--------------------------------------------------------------------------
                */

                'leave_minutes' =>
                    $totalLeaveMinutes,

                'leave_hours' =>
                    round(
                        $totalLeaveMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Available
                |--------------------------------------------------------------------------
                */

                'available_minutes' =>
                    $totalAvailableMinutes,

                'available_hours' =>
                    round(
                        $totalAvailableMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Worked Inside Schedule
                |--------------------------------------------------------------------------
                */

                'worked_minutes' =>
                    $totalWorkedMinutes,

                'worked_hours' =>
                    round(
                        $totalWorkedMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Raw Worked
                |--------------------------------------------------------------------------
                */

                'raw_worked_minutes' =>
                    $totalRawWorkedMinutes,

                'raw_worked_hours' =>
                    round(
                        $totalRawWorkedMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Outside Schedule
                |--------------------------------------------------------------------------
                */

                'outside_schedule_worked_minutes' =>
                    $totalOutsideScheduleWorkedMinutes,

                'outside_schedule_worked_hours' =>
                    round(
                        $totalOutsideScheduleWorkedMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Service Workload
                |--------------------------------------------------------------------------
                */

                'service_workload_minutes' =>
                    $totalServiceWorkloadMinutes,

                'service_workload_hours' =>
                    round(
                        $totalServiceWorkloadMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Empty
                |--------------------------------------------------------------------------
                */

                'empty_minutes' =>
                    $totalEmptyMinutes,

                'empty_hours' =>
                    round(
                        $totalEmptyMinutes / 60,
                        2
                    ),

                /*
                |--------------------------------------------------------------------------
                | Utilization
                |--------------------------------------------------------------------------
                */

                'utilization_percent' =>
                    $utilizationPercent,
            ],

            'days' =>
                $days,
        ];
    }
    /**
     * Detailed staff performance report.
     */
    public function staffDetailedReport(
        int $staffId,
        Carbon|string $from,
        Carbon|string $to
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Dates
        |--------------------------------------------------------------------------
        */

        $from = $from instanceof Carbon
            ? $from->copy()
            : Carbon::parse($from);

        $to = $to instanceof Carbon
            ? $to->copy()
            : Carbon::parse($to);

        $from->startOfDay();
        $to->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Actual Performance End Date
        |--------------------------------------------------------------------------
        */

        $actualTo = $to->copy();

        if ($actualTo->isAfter(today()->endOfDay())) {
            $actualTo = today()->endOfDay();
        }

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        $staff = \App\Models\Staff::query()
            ->findOrFail($staffId);

        /*
        |--------------------------------------------------------------------------
        | Actual Booking Services
        |--------------------------------------------------------------------------
        |
        | فقط خدمات انجام‌شده تا امروز.
        | رزروهای آینده وارد آمار عملکرد واقعی نمی‌شوند.
        |
        */

        $bookingServices = collect();

        if ($from->lte($actualTo)) {

            $bookingServices = \App\Models\BookingService::query()
                ->join(
                    'bookings',
                    'booking_services.booking_id',
                    '=',
                    'bookings.id'
                )
                ->join(
                    'services',
                    'booking_services.service_id',
                    '=',
                    'services.id'
                )
                ->where(
                    'booking_services.staff_id',
                    $staffId
                )
                ->whereBetween(
                    'bookings.booking_date',
                    [
                        $from->toDateString(),
                        $actualTo->toDateString(),
                    ]
                )
                ->whereNotIn(
                    'bookings.status',
                    ['cancelled']
                )
                ->select([
                    'booking_services.id',
                    'booking_services.booking_id',
                    'booking_services.service_id',
                    'booking_services.duration',
                    'booking_services.price',
                    'booking_services.start_time',
                    'booking_services.end_time',

                    'bookings.booking_date',
                    'bookings.client_id',

                    'services.name as service_name',
                ])
                ->orderBy('bookings.booking_date')
                ->orderBy('booking_services.start_time')
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | Actual General Totals
        |--------------------------------------------------------------------------
        */

        $bookingsCount = $bookingServices
            ->pluck('booking_id')
            ->unique()
            ->count();

        $clientsCount = $bookingServices
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->count();

        $servicesCount = $bookingServices->count();

        $servicesAmount = (float) $bookingServices
            ->sum('price');

        $totalMinutes = (int) $bookingServices
            ->sum('duration');

        $totalHours = round(
            $totalMinutes / 60,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Actual Working Days
        |--------------------------------------------------------------------------
        */

        $workingDates = $bookingServices
            ->pluck('booking_date')
            ->map(function ($date) {
                return Carbon::parse($date)
                    ->toDateString();
            })
            ->unique()
            ->sort()
            ->values();

        $workingDaysCount = $workingDates->count();

        /*
        |--------------------------------------------------------------------------
        | Actual Daily Performance
        |--------------------------------------------------------------------------
        */

        $daily = $bookingServices
            ->groupBy(function ($row) {
                return Carbon::parse(
                    $row->booking_date
                )->toDateString();
            })
            ->map(function ($rows, $date) {

                $dayBookingsCount = $rows
                    ->pluck('booking_id')
                    ->unique()
                    ->count();

                $dayClientsCount = $rows
                    ->pluck('client_id')
                    ->filter()
                    ->unique()
                    ->count();

                $dayServicesCount = $rows->count();

                $dayAmount = (float) $rows
                    ->sum('price');

                $dayMinutes = (int) $rows
                    ->sum('duration');

                return [
                    'date' => $date,

                    'bookings_count' =>
                        $dayBookingsCount,

                    'clients_count' =>
                        $dayClientsCount,

                    'services_count' =>
                        $dayServicesCount,

                    'services_amount' =>
                        $dayAmount,

                    'total_minutes' =>
                        $dayMinutes,

                    'total_hours' =>
                        round(
                            $dayMinutes / 60,
                            2
                        ),

                    'services' =>
                        $rows
                            ->pluck('service_name')
                            ->filter()
                            ->values()
                            ->toArray(),
                ];
            })
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Actual Service Breakdown
        |--------------------------------------------------------------------------
        */

        $serviceBreakdown = $bookingServices
            ->groupBy('service_id')
            ->map(function ($rows) {

                $minutes = (int) $rows
                    ->sum('duration');

                return [
                    'service_id' =>
                        (int) $rows->first()->service_id,

                    'service_name' =>
                        $rows->first()->service_name,

                    'count' =>
                        $rows->count(),

                    'amount' =>
                        (float) $rows->sum('price'),

                    'total_minutes' =>
                        $minutes,

                    'total_hours' =>
                        round(
                            $minutes / 60,
                            2
                        ),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Future Booking Services
        |--------------------------------------------------------------------------
        |
        | رزروهای بعد از امروز تا انتهای بازه انتخابی.
        | این بخش کاملاً از عملکرد واقعی جداست.
        |
        */

        $futureBookingServices = collect();

        $futureFrom = today()
            ->addDay()
            ->startOfDay();

        /*
        | اگر ابتدای بازه انتخابی خودش در آینده باشد،
        | رزروهای آینده را از همان ابتدای بازه حساب می‌کنیم.
        */

        if ($from->gt($futureFrom)) {
            $futureFrom = $from->copy()->startOfDay();
        }

        if ($futureFrom->lte($to)) {

            $futureBookingServices = \App\Models\BookingService::query()
                ->join(
                    'bookings',
                    'booking_services.booking_id',
                    '=',
                    'bookings.id'
                )
                ->join(
                    'services',
                    'booking_services.service_id',
                    '=',
                    'services.id'
                )
                ->where(
                    'booking_services.staff_id',
                    $staffId
                )
                ->whereBetween(
                    'bookings.booking_date',
                    [
                        $futureFrom->toDateString(),
                        $to->toDateString(),
                    ]
                )
                ->whereNotIn(
                    'bookings.status',
                    ['cancelled']
                )
                ->select([
                    'booking_services.id',
                    'booking_services.booking_id',
                    'booking_services.service_id',
                    'booking_services.duration',
                    'booking_services.price',
                    'booking_services.start_time',
                    'booking_services.end_time',

                    'bookings.booking_date',
                    'bookings.client_id',

                    'services.name as service_name',
                ])
                ->orderBy('bookings.booking_date')
                ->orderBy('booking_services.start_time')
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | Future Totals
        |--------------------------------------------------------------------------
        */

        $futureBookingsCount = $futureBookingServices
            ->pluck('booking_id')
            ->unique()
            ->count();

        $futureClientsCount = $futureBookingServices
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->count();

        $futureServicesCount = $futureBookingServices
            ->count();

        $futureServicesAmount = (float) $futureBookingServices
            ->sum('price');

        $futureTotalMinutes = (int) $futureBookingServices
            ->sum('duration');

        $futureTotalHours = round(
            $futureTotalMinutes / 60,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Future Dates
        |--------------------------------------------------------------------------
        */

        $futureDates = $futureBookingServices
            ->pluck('booking_date')
            ->map(function ($date) {
                return Carbon::parse($date)
                    ->toDateString();
            })
            ->unique()
            ->sort()
            ->values();

        $futureDaysCount = $futureDates->count();

        /*
        |--------------------------------------------------------------------------
        | Future Daily Performance
        |--------------------------------------------------------------------------
        */

        $futureDaily = $futureBookingServices
            ->groupBy(function ($row) {
                return Carbon::parse(
                    $row->booking_date
                )->toDateString();
            })
            ->map(function ($rows, $date) {

                $dayBookingsCount = $rows
                    ->pluck('booking_id')
                    ->unique()
                    ->count();

                $dayClientsCount = $rows
                    ->pluck('client_id')
                    ->filter()
                    ->unique()
                    ->count();

                $dayServicesCount = $rows->count();

                $dayAmount = (float) $rows
                    ->sum('price');

                $dayMinutes = (int) $rows
                    ->sum('duration');

                return [
                    'date' => $date,

                    'bookings_count' =>
                        $dayBookingsCount,

                    'clients_count' =>
                        $dayClientsCount,

                    'services_count' =>
                        $dayServicesCount,

                    'services_amount' =>
                        $dayAmount,

                    'total_minutes' =>
                        $dayMinutes,

                    'total_hours' =>
                        round(
                            $dayMinutes / 60,
                            2
                        ),

                    'services' =>
                        $rows
                            ->pluck('service_name')
                            ->filter()
                            ->values()
                            ->toArray(),
                ];
            })
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Future Service Breakdown
        |--------------------------------------------------------------------------
        */

        $futureServiceBreakdown = $futureBookingServices
            ->groupBy('service_id')
            ->map(function ($rows) {

                $minutes = (int) $rows
                    ->sum('duration');

                return [
                    'service_id' =>
                        (int) $rows->first()->service_id,

                    'service_name' =>
                        $rows->first()->service_name,

                    'count' =>
                        $rows->count(),

                    'amount' =>
                        (float) $rows->sum('price'),

                    'total_minutes' =>
                        $minutes,

                    'total_hours' =>
                        round(
                            $minutes / 60,
                            2
                        ),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Staff Leaves
        |--------------------------------------------------------------------------
        |
        | مرخصی‌ها برای کل بازه انتخابی نگه داشته می‌شوند.
        |
        */

        $leaves = \App\Models\StaffLeave::query()
            ->where(
                'staff_id',
                $staffId
            )
            ->whereBetween(
                'leave_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            )
            ->orderBy('leave_date')
            ->orderBy('start_time')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Leave Dates
        |--------------------------------------------------------------------------
        */

        $leaveDates = $leaves
            ->pluck('leave_date')
            ->map(function ($date) {
                return Carbon::parse($date)
                    ->toDateString();
            })
            ->unique()
            ->sort()
            ->values();

        $leaveDaysCount = $leaveDates->count();

        /*
        |--------------------------------------------------------------------------
        | Leave Details
        |--------------------------------------------------------------------------
        */

        $leaveDetails = $leaves
            ->map(function ($leave) {

                return [
                    'id' =>
                        (int) $leave->id,

                    'date' =>
                        Carbon::parse(
                            $leave->leave_date
                        )->toDateString(),

                    'start_time' =>
                        $leave->start_time,

                    'end_time' =>
                        $leave->end_time,
                ];
            })
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Average Per Actual Working Day
        |--------------------------------------------------------------------------
        */

        $averageRevenuePerWorkingDay =
            $workingDaysCount > 0
                ? round(
                $servicesAmount / $workingDaysCount,
                2
            )
                : 0;

        $averageBookingsPerWorkingDay =
            $workingDaysCount > 0
                ? round(
                $bookingsCount / $workingDaysCount,
                2
            )
                : 0;

        $averageServicesPerWorkingDay =
            $workingDaysCount > 0
                ? round(
                $servicesCount / $workingDaysCount,
                2
            )
                : 0;

        $averageHoursPerWorkingDay =
            $workingDaysCount > 0
                ? round(
                $totalHours / $workingDaysCount,
                2
            )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        return [

            'staff' => [
                'id' =>
                    (int) $staff->id,

                'name' =>
                    $staff->name,
            ],

            'period' => [
                'from' =>
                    $from->toDateString(),

                'to' =>
                    $to->toDateString(),

                'actual_to' =>
                    $actualTo->toDateString(),

                'future_from' =>
                    $futureFrom->toDateString(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Actual Performance
            |--------------------------------------------------------------------------
            */

            'summary' => [

                'working_days_count' =>
                    $workingDaysCount,

                'leave_days_count' =>
                    $leaveDaysCount,

                'bookings_count' =>
                    $bookingsCount,

                'clients_count' =>
                    $clientsCount,

                'services_count' =>
                    $servicesCount,

                'services_amount' =>
                    $servicesAmount,

                'total_minutes' =>
                    $totalMinutes,

                'total_hours' =>
                    $totalHours,

                'average_revenue_per_working_day' =>
                    $averageRevenuePerWorkingDay,

                'average_bookings_per_working_day' =>
                    $averageBookingsPerWorkingDay,

                'average_services_per_working_day' =>
                    $averageServicesPerWorkingDay,

                'average_hours_per_working_day' =>
                    $averageHoursPerWorkingDay,
            ],

            /*
            |--------------------------------------------------------------------------
            | Future Performance
            |--------------------------------------------------------------------------
            */

            'future_summary' => [

                'days_count' =>
                    $futureDaysCount,

                'bookings_count' =>
                    $futureBookingsCount,

                'clients_count' =>
                    $futureClientsCount,

                'services_count' =>
                    $futureServicesCount,

                'services_amount' =>
                    $futureServicesAmount,

                'total_minutes' =>
                    $futureTotalMinutes,

                'total_hours' =>
                    $futureTotalHours,
            ],

            /*
            |--------------------------------------------------------------------------
            | Actual Details
            |--------------------------------------------------------------------------
            */

            'working_dates' =>
                $workingDates->toArray(),

            'daily' =>
                $daily,

            'service_breakdown' =>
                $serviceBreakdown,

            /*
            |--------------------------------------------------------------------------
            | Future Details
            |--------------------------------------------------------------------------
            */

            'future_dates' =>
                $futureDates->toArray(),

            'future_daily' =>
                $futureDaily,

            'future_service_breakdown' =>
                $futureServiceBreakdown,

            /*
            |--------------------------------------------------------------------------
            | Leaves
            |--------------------------------------------------------------------------
            */

            'leave_dates' =>
                $leaveDates->toArray(),

            'leaves' =>
                $leaveDetails,
        ];
    }
}
