@php
    use App\Helpers\JalaliHelper;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">

    <title>گزارش حسابداری NIL</title>

    <style>
        body {
            direction: rtl;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
        }

        h1,
        h2,
        h3 {
            margin: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 22px;
            margin-bottom: 8px;
        }

        .date-range {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        .filter-info {
            margin-top: 10px;
            font-size: 11px;
        }

        .section-title {
            font-size: 15px;
            margin-top: 25px;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }

        .sub-title {
            font-size: 13px;
            margin-top: 18px;
            margin-bottom: 7px;
        }

        .summary-table,
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .summary-table th,
        .summary-table td,
        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: right;
            vertical-align: middle;
        }

        .summary-table th,
        .report-table th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .amount {
            direction: ltr;
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .ltr {
            direction: ltr;
        }

        .page-break {
            page-break-before: always;
        }

        .keep-together {
            page-break-inside: avoid;
        }

        .small {
            font-size: 9px;
        }

        .muted {
            color: #777;
        }

        .warning {
            color: #a94442;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #777;
        }
    </style>
</head>

<body>

{{-- ========================================================= --}}
{{-- Header --}}
{{-- ========================================================= --}}

<div class="header">

    <h1>
        NIL Beauty Salon
    </h1>

    <div>
        گزارش حسابداری
    </div>

    <div class="date-range">
        از {{ JalaliHelper::date($from, 'Y/m/d') }}
        تا {{ JalaliHelper::date($to, 'Y/m/d') }}
    </div>

    @if($selectedStaff || $selectedService)

        <div class="filter-info">

            @if($selectedStaff)
                پرسنل:
                <strong>
                    {{ $selectedStaff->name }}
                </strong>
            @endif

            @if($selectedStaff && $selectedService)
                |
            @endif

            @if($selectedService)
                خدمت:
                <strong>
                    {{ $selectedService->name }}
                </strong>
            @endif

        </div>

    @endif

</div>


{{-- ========================================================= --}}
{{-- Financial Summary --}}
{{-- ========================================================= --}}

<h2 class="section-title">
    خلاصه مالی
</h2>

<table class="summary-table">

    <thead>
    <tr>
        <th>عنوان</th>
        <th>مبلغ</th>
    </tr>
    </thead>

    <tbody>

    <tr>
        <td>درآمد</td>

        <td class="amount">
            {{ number_format($summary['income'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>هزینه</td>

        <td class="amount">
            {{ number_format($summary['expense'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>برگشت وجه</td>

        <td class="amount">
            {{ number_format($summary['refund'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>درآمد خالص</td>

        <td class="amount">
            {{ number_format($summary['net_revenue'] ?? 0) }}
            تومان
        </td>
    </tr>

    </tbody>

</table>


{{-- ========================================================= --}}
{{-- Payment Methods --}}
{{-- ========================================================= --}}

<h2 class="section-title">
    روش‌های پرداخت
</h2>

<table class="report-table">

    <thead>
    <tr>
        <th>روش پرداخت</th>
        <th>مبلغ</th>
    </tr>
    </thead>

    <tbody>

    <tr>
        <td>آنلاین</td>

        <td>
            {{ number_format($summary['payment_methods']['online'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>نقدی</td>

        <td>
            {{ number_format($summary['payment_methods']['cash'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>کارتخوان</td>

        <td>
            {{ number_format($summary['payment_methods']['pos'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>انتقال بانکی</td>

        <td>
            {{ number_format($summary['payment_methods']['bank_transfer'] ?? 0) }}
            تومان
        </td>
    </tr>

    <tr>
        <td>سایر</td>

        <td>
            {{ number_format($summary['payment_methods']['other'] ?? 0) }}
            تومان
        </td>
    </tr>

    </tbody>

</table>


{{-- ========================================================= --}}
{{-- Staff General Report --}}
{{-- ========================================================= --}}

<h2 class="section-title">
    عملکرد پرسنل
</h2>

<table class="report-table">

    <thead>

    <tr>
        <th>پرسنل</th>
        <th>رزرو</th>
        <th>خدمات</th>
        <th>مبلغ خدمات</th>
        <th>ساعت</th>
    </tr>

    </thead>

    <tbody>

    @forelse($staff as $row)

        <tr>

            <td>
                {{ $row['staff_name'] ?? '-' }}
            </td>

            <td>
                {{ number_format($row['bookings_count'] ?? 0) }}
            </td>

            <td>
                {{ number_format($row['services_count'] ?? 0) }}
            </td>

            <td>
                {{ number_format($row['services_amount'] ?? 0) }}
                تومان
            </td>

            <td>
                {{ $row['total_hours'] ?? 0 }}
            </td>

        </tr>

    @empty

        <tr>
            <td colspan="5">
                اطلاعاتی وجود ندارد.
            </td>
        </tr>

    @endforelse

    </tbody>

</table>


{{-- ========================================================= --}}
{{-- Services General Report --}}
{{-- ========================================================= --}}

<h2 class="section-title">
    عملکرد خدمات
</h2>

<table class="report-table">

    <thead>

    <tr>
        <th>خدمت</th>
        <th>رزرو</th>
        <th>تعداد</th>
        <th>مبلغ</th>
        <th>ساعت</th>
    </tr>

    </thead>

    <tbody>

    @forelse($services as $row)

        <tr>

            <td>
                {{ $row['service_name'] ?? '-' }}
            </td>

            <td>
                {{ number_format($row['bookings_count'] ?? 0) }}
            </td>

            <td>
                {{ number_format($row['services_count'] ?? 0) }}
            </td>

            <td>
                {{ number_format($row['services_amount'] ?? 0) }}
                تومان
            </td>

            <td>
                {{ $row['total_hours'] ?? 0 }}
            </td>

        </tr>

    @empty

        <tr>
            <td colspan="5">
                اطلاعاتی وجود ندارد.
            </td>
        </tr>

    @endforelse

    </tbody>

</table>


{{-- ========================================================= --}}
{{-- Detailed Staff Report --}}
{{-- ========================================================= --}}

@if(
    $selectedStaff &&
    !empty($staffDetailedReport)
)

    @php

        $staffSummary =
            $staffDetailedReport['summary'] ?? [];

        $futureSummary =
            $staffDetailedReport['future_summary'] ?? [];

        $scheduleSummary =
            $staffScheduleReport['summary'] ?? [];

        $daily =
            $staffDetailedReport['daily'] ?? [];

        $futureDaily =
            $staffDetailedReport['future_daily'] ?? [];

        $serviceBreakdown =
            $staffDetailedReport['service_breakdown'] ?? [];

        $leaves =
            $staffDetailedReport['leaves'] ?? [];

        $scheduleDays =
            $staffScheduleReport['days'] ?? [];

    @endphp


    <div class="page-break"></div>


    {{-- ===================================================== --}}
    {{-- Staff Header --}}
    {{-- ===================================================== --}}

    <h2 class="section-title">
        گزارش کامل پرسنل:
        {{ $selectedStaff->name }}
    </h2>


    {{-- ===================================================== --}}
    {{-- Performance Summary --}}
    {{-- ===================================================== --}}

    <h3 class="sub-title">
        خلاصه عملکرد
    </h3>

    <table class="report-table">

        <thead>

        <tr>
            <th>عنوان</th>
            <th>مقدار</th>
        </tr>

        </thead>

        <tbody>

        <tr>
            <td>روزهای دارای فعالیت</td>

            <td>
                {{ number_format($staffSummary['working_days_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>روزهای مرخصی</td>

            <td>
                {{ number_format($staffSummary['leave_days_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>تعداد رزرو</td>

            <td>
                {{ number_format($staffSummary['bookings_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>تعداد مشتری</td>

            <td>
                {{ number_format($staffSummary['clients_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>تعداد خدمات</td>

            <td>
                {{ number_format($staffSummary['services_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>مبلغ خدمات</td>

            <td>
                {{ number_format($staffSummary['services_amount'] ?? 0) }}
                تومان
            </td>
        </tr>

        <tr>
            <td>مجموع ساعت خدمات</td>

            <td>
                {{ $staffSummary['total_hours'] ?? 0 }}
                ساعت
            </td>
        </tr>

        <tr>
            <td>میانگین درآمد روز کاری</td>

            <td>
                {{ number_format($staffSummary['average_revenue_per_working_day'] ?? 0) }}
                تومان
            </td>
        </tr>

        <tr>
            <td>میانگین رزرو در روز</td>

            <td>
                {{ $staffSummary['average_bookings_per_working_day'] ?? 0 }}
            </td>
        </tr>

        <tr>
            <td>میانگین خدمات در روز</td>

            <td>
                {{ $staffSummary['average_services_per_working_day'] ?? 0 }}
            </td>
        </tr>

        <tr>
            <td>میانگین ساعت خدمات در روز</td>

            <td>
                {{ $staffSummary['average_hours_per_working_day'] ?? 0 }}
                ساعت
            </td>
        </tr>

        </tbody>

    </table>


    {{-- ===================================================== --}}
    {{-- Schedule Summary --}}
    {{-- ===================================================== --}}

    @if(!empty($staffScheduleReport))

        <h3 class="sub-title">
            برنامه کاری و بهره‌وری
        </h3>

        <table class="report-table">

            <thead>

            <tr>
                <th>عنوان</th>
                <th>مقدار</th>
            </tr>

            </thead>

            <tbody>

            <tr>
                <td>روزهای برنامه‌ریزی‌شده گذشته</td>

                <td>
                    {{ number_format($scheduleSummary['scheduled_days_count'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>روزهای کارکرد</td>

                <td>
                    {{ number_format($scheduleSummary['worked_days_count'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>روزهای بدون رزرو</td>

                <td>
                    {{ number_format($scheduleSummary['no_booking_days_count'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>روزهای برنامه‌ریزی‌شده آینده</td>

                <td>
                    {{ number_format($scheduleSummary['future_scheduled_days_count'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>ساعات برنامه کاری</td>

                <td>
                    {{ $scheduleSummary['scheduled_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>زمان استراحت</td>

                <td>
                    {{ $scheduleSummary['break_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>زمان مرخصی</td>

                <td>
                    {{ $scheduleSummary['leave_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>زمان قابل کار</td>

                <td>
                    {{ $scheduleSummary['available_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>کارکرد واقعی داخل برنامه</td>

                <td>
                    {{ $scheduleSummary['worked_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>مجموع بار کاری خدمات</td>

                <td>
                    {{ $scheduleSummary['service_workload_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>کار ثبت‌شده خارج از زمان قابل کار</td>

                <td class="{{ ($scheduleSummary['outside_schedule_worked_minutes'] ?? 0) > 0 ? 'warning' : '' }}">
                    {{ $scheduleSummary['outside_schedule_worked_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>زمان خالی</td>

                <td>
                    {{ $scheduleSummary['empty_hours'] ?? 0 }}
                    ساعت
                </td>
            </tr>

            <tr>
                <td>بهره‌وری</td>

                <td>
                    {{ number_format($scheduleSummary['utilization_percent'] ?? 0, 2) }}
                    %
                </td>
            </tr>

            </tbody>

        </table>

    @endif


    {{-- ===================================================== --}}
    {{-- Daily Schedule --}}
    {{-- ===================================================== --}}

    @if(!empty($scheduleDays))

        <h3 class="sub-title">
            جزئیات برنامه کاری روزانه
        </h3>

        <table class="report-table small">

            <thead>

            <tr>
                <th>تاریخ</th>
                <th>وضعیت</th>
                <th>برنامه</th>
                <th>استراحت</th>
                <th>مرخصی</th>
                <th>قابل کار</th>
                <th>کارکرد</th>
                <th>خارج برنامه</th>
                <th>بهره‌وری</th>
            </tr>

            </thead>

            <tbody>

            @foreach($scheduleDays as $day)

                @php

                    $statusLabel = match(
                        $day['status'] ?? ''
                    ) {
                        'worked' =>
                            'کارکرد',

                        'worked_and_leave' =>
                            'کارکرد + مرخصی',

                        'leave' =>
                            'مرخصی',

                        'no_booking' =>
                            'بدون رزرو',

                        'scheduled' =>
                            'برنامه آینده',

                        default =>
                            'تعطیل',
                    };

                @endphp

                <tr>

                    <td class="ltr">
                        {{ !empty($day['date']) ? JalaliHelper::date($day['date'], 'Y/m/d') : '-' }}
                    </td>

                    <td>
                        {{ $statusLabel }}
                    </td>

                    <td>
                        {{ $day['scheduled_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ $day['break_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ $day['leave_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ $day['available_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ $day['worked_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ $day['outside_schedule_worked_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ number_format($day['utilization_percent'] ?? 0, 2) }}
                        %
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    @endif


    {{-- ===================================================== --}}
    {{-- Daily Performance --}}
    {{-- ===================================================== --}}

    @if(!empty($daily))

        <h3 class="sub-title">
            عملکرد روزانه
        </h3>

        <table class="report-table">

            <thead>

            <tr>
                <th>تاریخ</th>
                <th>رزرو</th>
                <th>مشتری</th>
                <th>خدمات</th>
                <th>ساعت</th>
                <th>مبلغ خدمات</th>
            </tr>

            </thead>

            <tbody>

            @foreach($daily as $row)

                <tr>

                    <td class="ltr">
                        {{ !empty($row['date']) ? JalaliHelper::date($row['date'], 'Y/m/d') : '-' }}
                    </td>

                    <td>
                        {{ number_format($row['bookings_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ number_format($row['clients_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ number_format($row['services_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ $row['total_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ number_format($row['services_amount'] ?? 0) }}
                        تومان
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    @endif


    {{-- ===================================================== --}}
    {{-- Service Breakdown --}}
    {{-- ===================================================== --}}

    @if(!empty($serviceBreakdown))

        <h3 class="sub-title">
            تفکیک خدمات پرسنل
        </h3>

        <table class="report-table">

            <thead>

            <tr>
                <th>خدمت</th>
                <th>تعداد</th>
                <th>رزرو</th>
                <th>ساعت</th>
                <th>مبلغ</th>
            </tr>

            </thead>

            <tbody>

            @foreach($serviceBreakdown as $row)

                <tr>

                    <td>
                        {{ $row['service_name'] ?? '-' }}
                    </td>

                    <td>
                        {{ number_format($row['services_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ number_format($row['bookings_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ $row['total_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ number_format($row['services_amount'] ?? 0) }}
                        تومان
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    @endif


    {{-- ===================================================== --}}
    {{-- Leaves --}}
    {{-- ===================================================== --}}

    @if(!empty($leaves))

        <h3 class="sub-title">
            مرخصی‌ها
        </h3>

        <table class="report-table">

            <thead>

            <tr>
                <th>تاریخ</th>
                <th>شروع</th>
                <th>پایان</th>
            </tr>

            </thead>

            <tbody>

            @foreach($leaves as $leave)

                <tr>

                    <td class="ltr">
                        {{ !empty($leave['date'] ?? $leave['leave_date'] ?? null)
    ? JalaliHelper::date($leave['date'] ?? $leave['leave_date'], 'Y/m/d')
    : '-'
}}
                    </td>

                    <td class="ltr">
                        {{ $leave['start_time'] ?? '-' }}
                    </td>

                    <td class="ltr">
                        {{ $leave['end_time'] ?? '-' }}
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    @endif


    {{-- ===================================================== --}}
    {{-- Future Bookings --}}
    {{-- ===================================================== --}}

    <h3 class="sub-title">
        برنامه رزروهای آینده
    </h3>

    <table class="report-table">

        <thead>

        <tr>
            <th>عنوان</th>
            <th>مقدار</th>
        </tr>

        </thead>

        <tbody>

        <tr>
            <td>روزهای دارای رزرو آینده</td>

            <td>
                {{ number_format($futureSummary['days_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>رزروهای آینده</td>

            <td>
                {{ number_format($futureSummary['bookings_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>مشتریان آینده</td>

            <td>
                {{ number_format($futureSummary['clients_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>خدمات آینده</td>

            <td>
                {{ number_format($futureSummary['services_count'] ?? 0) }}
            </td>
        </tr>

        <tr>
            <td>ساعت خدمات آینده</td>

            <td>
                {{ $futureSummary['total_hours'] ?? 0 }}
                ساعت
            </td>
        </tr>

        <tr>
            <td>ارزش خدمات آینده</td>

            <td>
                {{ number_format($futureSummary['services_amount'] ?? 0) }}
                تومان
            </td>
        </tr>

        </tbody>

    </table>


    {{-- ===================================================== --}}
    {{-- Future Daily --}}
    {{-- ===================================================== --}}

    @if(!empty($futureDaily))

        <h3 class="sub-title">
            جزئیات رزروهای آینده
        </h3>

        <table class="report-table">

            <thead>

            <tr>
                <th>تاریخ</th>
                <th>رزرو</th>
                <th>مشتری</th>
                <th>خدمات</th>
                <th>ساعت</th>
                <th>مبلغ</th>
            </tr>

            </thead>

            <tbody>

            @foreach($futureDaily as $row)

                <tr>

                    <td class="ltr">
                        {{ !empty($row['date']) ? JalaliHelper::date($row['date'], 'Y/m/d') : '-' }}
                    </td>

                    <td>
                        {{ number_format($row['bookings_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ number_format($row['clients_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ number_format($row['services_count'] ?? 0) }}
                    </td>

                    <td>
                        {{ $row['total_hours'] ?? 0 }}
                    </td>

                    <td>
                        {{ number_format($row['services_amount'] ?? 0) }}
                        تومان
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    @endif

@endif


{{-- ========================================================= --}}
{{-- Footer --}}
{{-- ========================================================= --}}

<div class="footer">

    NIL Beauty Salon — Accounting System

    <br>

    تاریخ ایجاد گزارش:
    {{ JalaliHelper::nowDateTime('Y/m/d H:i') }}

</div>

</body>

</html>
