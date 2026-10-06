<x-filament-panels::page>


    <div class="space-y-6 md:space-y-8">

        {{-- ========================================================= --}}
        {{-- Custom Report Filters --}}
        {{-- ========================================================= --}}

        <x-filament::section>

            <x-slot name="heading">
        <span class="text-lg font-bold text-gray-950 dark:text-white">
            گزارش سفارشی
        </span>
            </x-slot>

            <x-slot name="description">
        <span class="font-medium text-gray-600 dark:text-gray-300">
            بازه زمانی، پرسنل یا خدمت موردنظر را انتخاب کنید.
        </span>
            </x-slot>

            <form
                wire:submit="loadCustomReport"
                class="space-y-5"
            >

                {{-- Filters --}}
                <div
                    class="
                rounded-xl
                border border-gray-200
                bg-white
                p-4
                shadow-sm
                dark:border-gray-700
                dark:bg-gray-900
                sm:p-5
            "
                >

                    {{-- Filament Form Fields --}}
                    <div>
                        {{ $this->form }}
                    </div>


                    {{-- ================================================= --}}
                    {{-- Actions --}}
                    {{-- ================================================= --}}

                    <div
                        class="
                    mt-6
                    flex
                    flex-col
                    gap-3
                    border-t
                    border-gray-200
                    pt-5
                    dark:border-gray-700
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                "
                    >

                        {{-- Apply Filter --}}
                        <div class="w-full sm:w-auto">

                            <x-filament::button
                                type="submit"
                                icon="heroicon-o-funnel"
                                wire:loading.attr="disabled"
                                wire:target="loadCustomReport"
                                class="w-full sm:w-auto"
                            >

                        <span
                            wire:loading.remove
                            wire:target="loadCustomReport"
                        >
                            اعمال فیلتر
                        </span>

                                <span
                                    wire:loading
                                    wire:target="loadCustomReport"
                                >
                            در حال بارگذاری...
                        </span>

                            </x-filament::button>

                        </div>


                        {{-- Export Buttons --}}
                        <div
                            class="
                        grid
                        w-full
                        grid-cols-2
                        gap-2
                        sm:flex
                        sm:w-auto
                        sm:items-center
                    "
                        >

                            {{-- Excel --}}
                            <x-filament::button
                                type="button"
                                wire:click="exportExcel"
                                wire:loading.attr="disabled"
                                wire:target="exportExcel"
                                icon="heroicon-o-table-cells"
                                color="success"
                                outlined
                                class="w-full sm:w-auto"
                            >

                        <span
                            wire:loading.remove
                            wire:target="exportExcel"
                        >
                            دانلود Excel
                        </span>

                                <span
                                    wire:loading
                                    wire:target="exportExcel"
                                >
                            در حال ساخت...
                        </span>

                            </x-filament::button>


                            {{-- PDF --}}
                            <x-filament::button
                                type="button"
                                wire:click="exportPdf"
                                wire:loading.attr="disabled"
                                wire:target="exportPdf"
                                icon="heroicon-o-document-arrow-down"
                                color="danger"
                                outlined
                                class="w-full sm:w-auto"
                            >

                        <span
                            wire:loading.remove
                            wire:target="exportPdf"
                        >
                            دانلود PDF
                        </span>

                                <span
                                    wire:loading
                                    wire:target="exportPdf"
                                >
                            در حال ساخت...
                        </span>

                            </x-filament::button>

                        </div>

                    </div>

                </div>

            </form>

        </x-filament::section>

        {{-- ========================================================= --}}
        {{-- Custom Summary --}}
        {{-- ========================================================= --}}

        <div>

            <h2
                class="
                    mb-4
                    text-lg
                    font-bold
                    text-gray-950
                    dark:text-white
                    sm:text-xl
                "
            >
                نتیجه بازه انتخاب‌شده
            </h2>

            <div
                class="
                    grid
                    grid-cols-1
                    gap-3
                    sm:grid-cols-2
                    xl:grid-cols-4
                "
            >

                <x-filament::section>

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        درآمد
                    </div>

                    <div
                        class="
                            mt-2
                            break-words
                            text-xl
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-2xl
                        "
                    >
                        {{ number_format($customSummary['income'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        هزینه
                    </div>

                    <div
                        class="
                            mt-2
                            break-words
                            text-xl
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-2xl
                        "
                    >
                        {{ number_format($customSummary['expense'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        برگشت وجه
                    </div>

                    <div
                        class="
                            mt-2
                            break-words
                            text-xl
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-2xl
                        "
                    >
                        {{ number_format($customSummary['refund'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        درآمد خالص
                    </div>

                    <div
                        class="
                            mt-2
                            break-words
                            text-xl
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-2xl
                        "
                    >
                        {{ number_format($customSummary['net_revenue'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- Detailed Staff Report --}}
        {{-- ========================================================= --}}

        @if(!empty($staffDetailedReport))

            <x-filament::section>

                <x-slot name="heading">
                    گزارش عملکرد پرسنل
                </x-slot>

                <x-slot name="description">

                    {{ $staffDetailedReport['staff']['name'] ?? '-' }}

                    @if(!empty($staffDetailedReport['period']))

                        —
                        از
                        {{ \App\Helpers\JalaliHelper::date($staffDetailedReport['period']['from'] ?? null) ?? '-' }}
                        تا
                        {{ \App\Helpers\JalaliHelper::date($staffDetailedReport['period']['to'] ?? null) ?? '-' }}

                    @endif

                </x-slot>


                @php
                    $staffSummary = $staffDetailedReport['summary'] ?? [];
                    $futureStaffSummary = $staffDetailedReport['future_summary'] ?? [];
                @endphp


                {{-- Staff Main Stats --}}
                <div
                    class="
                        grid
                        grid-cols-2
                        gap-3
                        sm:grid-cols-3
                        xl:grid-cols-6
                    "
                >

                    <div
                        class="
                            rounded-xl
                            border
                            border-gray-200
                            p-3
                            dark:border-gray-700
                            sm:p-4
                        "
                    >

                        <div class="text-xs text-gray-500 sm:text-sm">
                            روز کارکرد
                        </div>

                        <div class="mt-1 text-xl font-bold">
                            {{ number_format($staffSummary['working_days_count'] ?? 0) }}
                        </div>

                    </div>


                    <div
                        class="
                            rounded-xl
                            border
                            border-gray-200
                            p-3
                            dark:border-gray-700
                            sm:p-4
                        "
                    >

                        <div class="text-xs text-gray-500 sm:text-sm">
                            روز مرخصی
                        </div>

                        <div class="mt-1 text-xl font-bold">
                            {{ number_format($staffSummary['leave_days_count'] ?? 0) }}
                        </div>

                    </div>


                    <div
                        class="
                            rounded-xl
                            border
                            border-gray-200
                            p-3
                            dark:border-gray-700
                            sm:p-4
                        "
                    >

                        <div class="text-xs text-gray-500 sm:text-sm">
                            رزرو
                        </div>

                        <div class="mt-1 text-xl font-bold">
                            {{ number_format($staffSummary['bookings_count'] ?? 0) }}
                        </div>

                    </div>


                    <div
                        class="
                            rounded-xl
                            border
                            border-gray-200
                            p-3
                            dark:border-gray-700
                            sm:p-4
                        "
                    >

                        <div class="text-xs text-gray-500 sm:text-sm">
                            مشتری
                        </div>

                        <div class="mt-1 text-xl font-bold">
                            {{ number_format($staffSummary['clients_count'] ?? 0) }}
                        </div>

                    </div>


                    <div
                        class="
                            rounded-xl
                            border
                            border-gray-200
                            p-3
                            dark:border-gray-700
                            sm:p-4
                        "
                    >

                        <div class="text-xs text-gray-500 sm:text-sm">
                            خدمات
                        </div>

                        <div class="mt-1 text-xl font-bold">
                            {{ number_format($staffSummary['services_count'] ?? 0) }}
                        </div>

                    </div>


                    <div
                        class="
                            rounded-xl
                            border
                            border-gray-200
                            p-3
                            dark:border-gray-700
                            sm:p-4
                        "
                    >

                        <div class="text-xs text-gray-500 sm:text-sm">
                            ساعت خدمات
                        </div>

                        <div class="mt-1 text-xl font-bold">
                            {{ number_format($staffSummary['total_hours'] ?? 0, 2) }}
                        </div>

                    </div>

                </div>


                {{-- Revenue --}}
                <div
                    class="
                        mt-4
                        rounded-xl
                        border
                        border-gray-200
                        p-4
                        dark:border-gray-700
                    "
                >

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        ارزش کل خدمات انجام‌شده
                    </div>

                    <div
                        class="
                            mt-2
                            break-words
                            text-xl
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-2xl
                        "
                    >
                        {{ number_format($staffSummary['services_amount'] ?? 0) }}

                        <span class="text-sm font-normal">
                            تومان
                        </span>
                    </div>

                </div>
                {{-- ================================================= --}}
                {{-- Future Staff Bookings --}}
                {{-- ================================================= --}}

                <div
                    class="
        mt-6
        border-t
        border-gray-200
        pt-6
        dark:border-gray-700
    "
                >

                    <div class="mb-4">

                        <h3
                            class="
                text-base
                font-bold
                text-gray-950
                dark:text-white
                sm:text-lg
            "
                        >
                            برنامه رزروهای آینده
                        </h3>

                        <p
                            class="
                mt-1
                text-xs
                text-gray-500
                dark:text-gray-400
                sm:text-sm
            "
                        >
                            رزروها و خدمات برنامه‌ریزی‌شده بعد از امروز
                        </p>

                    </div>


                    {{-- Future Summary Cards --}}
                    <div
                        class="
            grid
            grid-cols-2
            gap-3
            sm:grid-cols-3
            xl:grid-cols-6
        "
                    >

                        {{-- Days --}}
                        <div
                            class="
                rounded-xl
                border
                border-primary-200
                bg-primary-50
                p-3
                dark:border-primary-800
                dark:bg-primary-950/30
                sm:p-4
            "
                        >

                            <div
                                class="
                    text-xs
                    text-primary-600
                    dark:text-primary-400
                    sm:text-sm
                "
                            >
                                روز دارای رزرو
                            </div>

                            <div
                                class="
                    mt-1
                    text-xl
                    font-bold
                    text-primary-600
                    dark:text-primary-400
                "
                            >
                                {{ number_format($futureStaffSummary['days_count'] ?? 0) }}
                            </div>

                        </div>


                        {{-- Bookings --}}
                        <div
                            class="
                rounded-xl
                border
                border-gray-200
                p-3
                dark:border-gray-700
                sm:p-4
            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                رزرو آینده
                            </div>

                            <div class="mt-1 text-xl font-bold">
                                {{ number_format($futureStaffSummary['bookings_count'] ?? 0) }}
                            </div>

                        </div>


                        {{-- Clients --}}
                        <div
                            class="
                rounded-xl
                border
                border-gray-200
                p-3
                dark:border-gray-700
                sm:p-4
            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                مشتری
                            </div>

                            <div class="mt-1 text-xl font-bold">
                                {{ number_format($futureStaffSummary['clients_count'] ?? 0) }}
                            </div>

                        </div>


                        {{-- Services --}}
                        <div
                            class="
                rounded-xl
                border
                border-gray-200
                p-3
                dark:border-gray-700
                sm:p-4
            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                خدمات آینده
                            </div>

                            <div class="mt-1 text-xl font-bold">
                                {{ number_format($futureStaffSummary['services_count'] ?? 0) }}
                            </div>

                        </div>


                        {{-- Hours --}}
                        <div
                            class="
                rounded-xl
                border
                border-gray-200
                p-3
                dark:border-gray-700
                sm:p-4
            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                ساعت خدمات
                            </div>

                            <div class="mt-1 text-xl font-bold">
                                {{ number_format($futureStaffSummary['total_hours'] ?? 0, 2) }}
                            </div>

                        </div>


                        {{-- Amount --}}
                        <div
                            class="
                rounded-xl
                border
                border-success-200
                bg-success-50
                p-3
                dark:border-success-800
                dark:bg-success-950/30
                sm:p-4
            "
                        >

                            <div
                                class="
                    text-xs
                    text-success-600
                    dark:text-success-400
                    sm:text-sm
                "
                            >
                                ارزش خدمات آینده
                            </div>

                            <div
                                class="
                    mt-1
                    break-words
                    text-lg
                    font-bold
                    text-success-600
                    dark:text-success-400
                "
                            >
                                {{ number_format($futureStaffSummary['services_amount'] ?? 0) }}

                                <span class="text-xs font-normal">
                    تومان
                </span>
                            </div>

                        </div>

                    </div>


                    {{-- Future Daily Table --}}
                    <div
                        class="
            mt-4
            w-full
            overflow-x-auto
            rounded-xl
            border
            border-gray-200
            dark:border-gray-700
        "
                    >

                        <table class="min-w-[800px] w-full text-sm">

                            <thead class="bg-gray-50 dark:bg-gray-800">

                            <tr>

                                <th class="whitespace-nowrap p-3 text-right">
                                    تاریخ
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    رزرو
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    مشتری
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    خدمات
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    ساعت
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    مبلغ
                                </th>

                            </tr>

                            </thead>


                            <tbody>

                            @forelse($staffDetailedReport['future_daily'] ?? [] as $row)

                                <tr
                                    class="
                        border-t
                        border-gray-200
                        dark:border-gray-700
                    "
                                >

                                    <td class="whitespace-nowrap p-3 font-medium">
                                        {{ \App\Helpers\JalaliHelper::date($row['date'] ?? null) ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['bookings_count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['clients_count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['services_count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['total_hours'] ?? 0, 2) }}
                                        ساعت
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['services_amount'] ?? 0) }}
                                        تومان
                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td
                                        colspan="6"
                                        class="
                            p-6
                            text-center
                            text-gray-500
                            dark:text-gray-400
                        "
                                    >
                                        در این بازه رزرو آینده‌ای برای این پرسنل وجود ندارد.
                                    </td>

                                </tr>

                            @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

                {{-- ================================================= --}}
                {{-- Schedule / Utilization --}}
                {{-- ================================================= --}}

                @if(!empty($staffScheduleReport))

                    @php
                        $scheduleSummary = $staffScheduleReport['summary'] ?? [];
                    @endphp

                    <div
                        class="
                            mt-6
                            border-t
                            border-gray-200
                            pt-6
                            dark:border-gray-700
                        "
                    >

                        <div class="mb-4">

                            <h3
                                class="
                                    text-base
                                    font-bold
                                    text-gray-950
                                    dark:text-white
                                    sm:text-lg
                                "
                            >
                                عملکرد زمانی پرسنل
                            </h3>

                            <p
                                class="
                                    mt-1
                                    text-xs
                                    text-gray-500
                                    dark:text-gray-400
                                    sm:text-sm
                                "
                            >
                                برنامه کاری، زمان خدمات، زمان خالی و بهره‌وری
                            </p>

                        </div>


                        <div
                            class="
                                grid
                                grid-cols-2
                                gap-3
                                lg:grid-cols-4
                            "
                        >

                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-gray-200
                                    p-3
                                    dark:border-gray-700
                                    sm:p-4
                                "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    ساعت برنامه کاری
                                </div>

                                <div class="mt-1 text-xl font-bold sm:text-2xl">
                                    {{ number_format($scheduleSummary['scheduled_hours'] ?? 0, 2) }}
                                </div>

                            </div>


                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-gray-200
                                    p-3
                                    dark:border-gray-700
                                    sm:p-4
                                "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    ساعت خدمات
                                </div>

                                <div class="mt-1 text-xl font-bold sm:text-2xl">
                                    {{ number_format($scheduleSummary['worked_hours'] ?? 0, 2) }}
                                </div>

                            </div>


                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-gray-200
                                    p-3
                                    dark:border-gray-700
                                    sm:p-4
                                "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    ساعت خالی
                                </div>

                                <div class="mt-1 text-xl font-bold sm:text-2xl">
                                    {{ number_format($scheduleSummary['empty_hours'] ?? 0, 2) }}
                                </div>

                            </div>


                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-primary-200
                                    bg-primary-50
                                    p-3
                                    dark:border-primary-800
                                    dark:bg-primary-950/30
                                    sm:p-4
                                "
                            >

                                <div
                                    class="
                                        text-xs
                                        text-primary-600
                                        dark:text-primary-400
                                        sm:text-sm
                                    "
                                >
                                    بهره‌وری
                                </div>

                                <div
                                    class="
                                        mt-1
                                        text-xl
                                        font-bold
                                        text-primary-600
                                        dark:text-primary-400
                                        sm:text-2xl
                                    "
                                >
                                    {{ number_format($scheduleSummary['utilization_percent'] ?? 0, 2) }}%
                                </div>

                            </div>

                        </div>


                        {{-- Days --}}
                        {{-- Days --}}
                        <div
                            class="
        mt-3
        grid
        grid-cols-2
        gap-3
        md:grid-cols-3
        xl:grid-cols-5
    "
                        >

                            {{-- Scheduled Days --}}
                            <div
                                class="
            rounded-xl
            bg-gray-50
            p-3
            dark:bg-gray-800
            sm:p-4
        "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    روزهای کاری تا امروز
                                </div>

                                <div class="mt-1 text-lg font-bold">
                                    {{ number_format($scheduleSummary['scheduled_days_count'] ?? 0) }}
                                </div>

                            </div>


                            {{-- Worked Days --}}
                            <div
                                class="
            rounded-xl
            bg-gray-50
            p-3
            dark:bg-gray-800
            sm:p-4
        "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    روزهای کارکرد
                                </div>

                                <div
                                    class="
                mt-1
                text-lg
                font-bold
                text-success-600
                dark:text-success-400
            "
                                >
                                    {{ number_format($scheduleSummary['worked_days_count'] ?? 0) }}
                                </div>

                            </div>


                            {{-- No Booking --}}
                            <div
                                class="
            rounded-xl
            bg-gray-50
            p-3
            dark:bg-gray-800
            sm:p-4
        "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    روزهای بدون مشتری
                                </div>

                                <div class="mt-1 text-lg font-bold">
                                    {{ number_format($scheduleSummary['no_booking_days_count'] ?? 0) }}
                                </div>

                            </div>


                            {{-- Leave --}}
                            <div
                                class="
            rounded-xl
            bg-gray-50
            p-3
            dark:bg-gray-800
            sm:p-4
        "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    روزهای دارای مرخصی
                                </div>

                                <div
                                    class="
                mt-1
                text-lg
                font-bold
                text-warning-600
                dark:text-warning-400
            "
                                >
                                    {{ number_format($scheduleSummary['leave_days_count'] ?? 0) }}
                                </div>

                            </div>


                            {{-- Future Scheduled --}}
                            <div
                                class="
            col-span-2
            rounded-xl
            border
            border-primary-200
            bg-primary-50
            p-3
            dark:border-primary-800
            dark:bg-primary-950/30
            md:col-span-1
            sm:p-4
        "
                            >

                                <div
                                    class="
                text-xs
                text-primary-600
                dark:text-primary-400
                sm:text-sm
            "
                                >
                                    روزهای برنامه آینده
                                </div>

                                <div
                                    class="
                mt-1
                text-lg
                font-bold
                text-primary-600
                dark:text-primary-400
            "
                                >
                                    {{ number_format($scheduleSummary['future_scheduled_days_count'] ?? 0) }}
                                </div>

                            </div>

                        </div>
                        {{-- Extra Hours --}}
                        <div
                            class="
                                mt-3
                                grid
                                grid-cols-1
                                gap-3
                                sm:grid-cols-3
                            "
                        >

                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-gray-200
                                    p-3
                                    dark:border-gray-700
                                    sm:p-4
                                "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    ساعت استراحت
                                </div>

                                <div class="mt-1 font-bold">
                                    {{ number_format($scheduleSummary['break_hours'] ?? 0, 2) }}
                                    ساعت
                                </div>

                            </div>


                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-gray-200
                                    p-3
                                    dark:border-gray-700
                                    sm:p-4
                                "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    ساعت مرخصی
                                </div>

                                <div class="mt-1 font-bold">
                                    {{ number_format($scheduleSummary['leave_hours'] ?? 0, 2) }}
                                    ساعت
                                </div>

                            </div>


                            <div
                                class="
                                    rounded-xl
                                    border
                                    border-gray-200
                                    p-3
                                    dark:border-gray-700
                                    sm:p-4
                                "
                            >

                                <div class="text-xs text-gray-500 sm:text-sm">
                                    ساعت قابل کار
                                </div>

                                <div class="mt-1 font-bold">
                                    {{ number_format($scheduleSummary['available_hours'] ?? 0, 2) }}
                                    ساعت
                                </div>

                            </div>

                        </div>

                    </div>
                    {{-- ================================================= --}}
                    {{-- Daily Staff Schedule --}}
                    {{-- ================================================= --}}

                    <div
                        class="
        mt-6
        border-t
        border-gray-200
        pt-6
        dark:border-gray-700
    "
                    >

                        <div class="mb-4">

                            <h3
                                class="
                text-base
                font-bold
                text-gray-950
                dark:text-white
                sm:text-lg
            "
                            >
                                جزئیات روزانه حضور و عملکرد
                            </h3>

                            <p
                                class="
                mt-1
                text-xs
                text-gray-500
                dark:text-gray-400
                sm:text-sm
            "
                            >
                                برنامه کاری، مرخصی، ساعات خدمات، زمان خالی و بهره‌وری روزانه
                            </p>

                        </div>


                        <div
                            class="
            w-full
            overflow-x-auto
            rounded-xl
            border
            border-gray-200
            dark:border-gray-700
        "
                        >

                            <table class="min-w-[1100px] w-full text-sm">

                                <thead
                                    class="
                    bg-gray-50
                    dark:bg-gray-800
                "
                                >

                                <tr>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        تاریخ
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        وضعیت
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        برنامه کاری
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        زمان برنامه
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        استراحت
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        مرخصی
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        قابل کار
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        خدمات
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        زمان خالی
                                    </th>

                                    <th class="whitespace-nowrap p-3 text-right">
                                        بهره‌وری
                                    </th>

                                </tr>

                                </thead>


                                <tbody>

                                @forelse($staffScheduleReport['days'] ?? [] as $day)

                                    @php

                                        $status = $day['status'] ?? 'off';

                                        $statusLabel = match ($status) {
    'worked' => 'کارکرده',
    'worked_and_leave' => 'کارکرد + مرخصی',
    'leave' => 'مرخصی',
    'no_booking' => 'بدون مشتری',
    'scheduled' => 'برنامه آینده',
    'off' => 'تعطیل',
    default => $status,
};

                                        $statusClass = match ($status) {
                                            'worked' =>
                                                'bg-success-50 text-success-700 dark:bg-success-950/40 dark:text-success-400',

                                            'worked_and_leave' =>
                                                'bg-warning-50 text-warning-700 dark:bg-warning-950/40 dark:text-warning-400',

                                            'leave' =>
                                                'bg-warning-50 text-warning-700 dark:bg-warning-950/40 dark:text-warning-400',

                                            'no_booking' =>
                                                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

                                            'off' =>
                                                'bg-danger-50 text-danger-700 dark:bg-danger-950/40 dark:text-danger-400',

'scheduled' =>
    'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-400',
                                            default =>
                                                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                        };


                                        $scheduleText = collect($day['schedules'] ?? [])
                                            ->map(function ($schedule) {

                                                $start = substr(
                                                    $schedule['start_time'] ?? '',
                                                    0,
                                                    5
                                                );

                                                $end = substr(
                                                    $schedule['end_time'] ?? '',
                                                    0,
                                                    5
                                                );

                                                if (!$start || !$end) {
                                                    return null;
                                                }

                                                return $start . ' - ' . $end;

                                            })
                                            ->filter()
                                            ->implode(' / ');


                                        $leaveText = collect($day['leaves'] ?? [])
                                            ->map(function ($leave) {

                                                $start = substr(
                                                    $leave['start_time'] ?? '',
                                                    0,
                                                    5
                                                );

                                                $end = substr(
                                                    $leave['end_time'] ?? '',
                                                    0,
                                                    5
                                                );

                                                if (!$start || !$end) {
                                                    return null;
                                                }

                                                return $start . ' - ' . $end;

                                            })
                                            ->filter()
                                            ->implode(' / ');

                                    @endphp


                                    <tr
                                        class="
                            border-t
                            border-gray-200
                            transition
                            hover:bg-gray-50
                            dark:border-gray-700
                            dark:hover:bg-gray-800/50
                        "
                                    >

                                        {{-- Date --}}
                                        <td
                                            class="
                                whitespace-nowrap
                                p-3
                                font-medium
                                text-gray-950
                                dark:text-white
                            "
                                        >
                                            {{ \App\Helpers\JalaliHelper::date($day['date'] ?? null) ?? '-' }}
                                        </td>


                                        {{-- Status --}}
                                        <td class="whitespace-nowrap p-3">

                            <span
                                class="
                                    inline-flex
                                    items-center
                                    rounded-full
                                    px-2.5
                                    py-1
                                    text-xs
                                    font-semibold
                                    {{ $statusClass }}
                                "
                            >
                                {{ $statusLabel }}
                            </span>

                                        </td>


                                        {{-- Schedule --}}
                                        <td class="whitespace-nowrap p-3">

                                            @if($scheduleText)

                                                <span class="font-medium">
                                    {{ $scheduleText }}
                                </span>

                                            @else

                                                <span class="text-gray-400">
                                    -
                                </span>

                                            @endif

                                        </td>


                                        {{-- Scheduled Hours --}}
                                        <td class="whitespace-nowrap p-3">

                                            {{ number_format(
                                                ($day['scheduled_minutes'] ?? 0) / 60,
                                                2
                                            ) }}

                                            ساعت

                                        </td>


                                        {{-- Break --}}
                                        <td class="whitespace-nowrap p-3">

                                            {{ number_format(
                                                ($day['break_minutes'] ?? 0) / 60,
                                                2
                                            ) }}

                                            ساعت

                                        </td>


                                        {{-- Leave --}}
                                        <td class="whitespace-nowrap p-3">

                                            @if(($day['leave_minutes'] ?? 0) > 0)

                                                <div class="font-medium text-warning-600">
                                                    {{ number_format(
                                                        $day['leave_hours'] ?? 0,
                                                        2
                                                    ) }}
                                                    ساعت
                                                </div>

                                                @if($leaveText)

                                                    <div class="mt-1 text-xs text-gray-500">
                                                        {{ $leaveText }}
                                                    </div>

                                                @endif

                                            @else

                                                <span class="text-gray-400">
                                    -
                                </span>

                                            @endif

                                        </td>


                                        {{-- Available --}}
                                        <td class="whitespace-nowrap p-3">

                                            {{ number_format(
                                                $day['available_hours'] ?? 0,
                                                2
                                            ) }}

                                            ساعت

                                        </td>


                                        {{-- Worked --}}
                                        <td class="whitespace-nowrap p-3">

                                            @if(($day['worked_minutes'] ?? 0) > 0)

                                                <span
                                                    class="
                                        font-semibold
                                        text-success-600
                                        dark:text-success-400
                                    "
                                                >
                                    {{ number_format(
                                        $day['worked_hours'] ?? 0,
                                        2
                                    ) }}
                                    ساعت
                                </span>

                                            @else

                                                <span class="text-gray-400">
                                    -
                                </span>

                                            @endif

                                        </td>


                                        {{-- Empty --}}
                                        <td class="whitespace-nowrap p-3">

                                            {{ number_format(
                                                $day['empty_hours'] ?? 0,
                                                2
                                            ) }}

                                            ساعت

                                        </td>


                                        {{-- Utilization --}}
                                        <td class="whitespace-nowrap p-3">

                                            @php
                                                $utilization =
                                                    $day['utilization_percent'] ?? 0;
                                            @endphp

                                            <div class="flex min-w-[120px] items-center gap-2">

                                                <div
                                                    class="
                                        h-2
                                        flex-1
                                        overflow-hidden
                                        rounded-full
                                        bg-gray-200
                                        dark:bg-gray-700
                                    "
                                                >

                                                    <div
                                                        class="
                                            h-full
                                            rounded-full
                                            bg-primary-600
                                        "
                                                        style="
                                            width:
                                            {{ min(100, max(0, $utilization)) }}%
                                        "
                                                    ></div>

                                                </div>

                                                <span
                                                    class="
                                        min-w-[50px]
                                        text-left
                                        text-xs
                                        font-bold
                                    "
                                                >
                                    {{ number_format(
                                        $utilization,
                                        2
                                    ) }}%
                                </span>

                                            </div>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td
                                            colspan="10"
                                            class="
                                p-8
                                text-center
                                text-gray-500
                                dark:text-gray-400
                            "
                                        >
                                            اطلاعاتی برای این بازه وجود ندارد.
                                        </td>

                                    </tr>

                                @endforelse

                                </tbody>

                            </table>

                        </div>

                    </div>

                @endif


                {{-- ================================================= --}}
                {{-- Daily Performance --}}
                {{-- ================================================= --}}

                <div
                    class="
                        mt-6
                        border-t
                        border-gray-200
                        pt-6
                        dark:border-gray-700
                    "
                >

                    <h3
                        class="
                            mb-4
                            text-base
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-lg
                        "
                    >
                        عملکرد روزانه
                    </h3>

                    <div
                        class="
                            w-full
                            overflow-x-auto
                            rounded-xl
                            border
                            border-gray-200
                            dark:border-gray-700
                        "
                    >

                        <table class="min-w-[800px] w-full text-sm">

                            <thead
                                class="
                                    bg-gray-50
                                    dark:bg-gray-800
                                "
                            >

                            <tr>

                                <th class="whitespace-nowrap p-3 text-right">
                                    تاریخ
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    رزرو
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    مشتری
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    خدمات
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    ساعت
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    مبلغ
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            @forelse($staffDetailedReport['daily'] ?? [] as $row)

                                <tr
                                    class="
                                            border-t
                                            border-gray-200
                                            dark:border-gray-700
                                        "
                                >

                                    <td class="whitespace-nowrap p-3 font-medium">
                                        {{ \App\Helpers\JalaliHelper::date($row['date'] ?? null) ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['bookings_count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['clients_count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['services_count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['total_hours'] ?? 0, 2) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['services_amount'] ?? 0) }}
                                        تومان
                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td
                                        colspan="6"
                                        class="p-6 text-center text-gray-500"
                                    >
                                        اطلاعاتی وجود ندارد.
                                    </td>

                                </tr>

                            @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- Service Breakdown --}}
                {{-- ================================================= --}}

                <div
                    class="
                        mt-6
                        border-t
                        border-gray-200
                        pt-6
                        dark:border-gray-700
                    "
                >

                    <h3
                        class="
                            mb-4
                            text-base
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-lg
                        "
                    >
                        تفکیک خدمات پرسنل
                    </h3>

                    <div
                        class="
                            w-full
                            overflow-x-auto
                            rounded-xl
                            border
                            border-gray-200
                            dark:border-gray-700
                        "
                    >

                        <table class="min-w-[650px] w-full text-sm">

                            <thead class="bg-gray-50 dark:bg-gray-800">

                            <tr>

                                <th class="whitespace-nowrap p-3 text-right">
                                    خدمت
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    تعداد
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    ساعت
                                </th>

                                <th class="whitespace-nowrap p-3 text-right">
                                    مبلغ
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            @forelse($staffDetailedReport['service_breakdown'] ?? [] as $row)

                                <tr
                                    class="
                                            border-t
                                            border-gray-200
                                            dark:border-gray-700
                                        "
                                >

                                    <td class="p-3 font-medium">
                                        {{ $row['service_name'] ?? $row['name'] ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['count'] ?? 0) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['hours'] ?? 0, 2) }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ number_format($row['amount'] ?? 0) }}
                                        تومان
                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td
                                        colspan="4"
                                        class="p-6 text-center text-gray-500"
                                    >
                                        اطلاعاتی وجود ندارد.
                                    </td>

                                </tr>

                            @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- Leaves --}}
                {{-- ================================================= --}}

                <div
                    class="
                        mt-6
                        border-t
                        border-gray-200
                        pt-6
                        dark:border-gray-700
                    "
                >

                    <h3
                        class="
                            mb-4
                            text-base
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-lg
                        "
                    >
                        مرخصی‌ها
                    </h3>

                    <div
                        class="
                            w-full
                            overflow-x-auto
                            rounded-xl
                            border
                            border-gray-200
                            dark:border-gray-700
                        "
                    >

                        <table class="min-w-[500px] w-full text-sm">

                            <thead class="bg-gray-50 dark:bg-gray-800">

                            <tr>

                                <th class="p-3 text-right">
                                    تاریخ
                                </th>

                                <th class="p-3 text-right">
                                    شروع
                                </th>

                                <th class="p-3 text-right">
                                    پایان
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            @forelse($staffDetailedReport['leaves'] ?? [] as $row)

                                <tr
                                    class="
                                            border-t
                                            border-gray-200
                                            dark:border-gray-700
                                        "
                                >

                                    <td class="whitespace-nowrap p-3">
                                        {{ \App\Helpers\JalaliHelper::date($row['date'] ?? null) ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ $row['start_time'] ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap p-3">
                                        {{ $row['end_time'] ?? '-' }}
                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td
                                        colspan="3"
                                        class="p-6 text-center text-gray-500"
                                    >
                                        در این بازه مرخصی ثبت نشده است.
                                    </td>

                                </tr>

                            @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- Average Performance --}}
                {{-- ================================================= --}}

                <div
                    class="
                        mt-6
                        border-t
                        border-gray-200
                        pt-6
                        dark:border-gray-700
                    "
                >

                    <h3
                        class="
                            mb-4
                            text-base
                            font-bold
                            text-gray-950
                            dark:text-white
                            sm:text-lg
                        "
                    >
                        میانگین عملکرد
                    </h3>

                    <div
                        class="
                            grid
                            grid-cols-1
                            gap-3
                            sm:grid-cols-2
                            xl:grid-cols-4
                        "
                    >

                        <div
                            class="
                                rounded-xl
                                bg-gray-50
                                p-4
                                dark:bg-gray-800
                            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                میانگین مبلغ روز کاری
                            </div>

                            <div class="mt-1 break-words font-bold">
                                {{ number_format($staffSummary['average_revenue_per_working_day'] ?? 0) }}
                                تومان
                            </div>

                        </div>


                        <div
                            class="
                                rounded-xl
                                bg-gray-50
                                p-4
                                dark:bg-gray-800
                            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                میانگین رزرو روزانه
                            </div>

                            <div class="mt-1 font-bold">
                                {{ number_format($staffSummary['average_bookings_per_working_day'] ?? 0, 2) }}
                            </div>

                        </div>


                        <div
                            class="
                                rounded-xl
                                bg-gray-50
                                p-4
                                dark:bg-gray-800
                            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                میانگین خدمات روزانه
                            </div>

                            <div class="mt-1 font-bold">
                                {{ number_format($staffSummary['average_services_per_working_day'] ?? 0, 2) }}
                            </div>

                        </div>


                        <div
                            class="
                                rounded-xl
                                bg-gray-50
                                p-4
                                dark:bg-gray-800
                            "
                        >

                            <div class="text-xs text-gray-500 sm:text-sm">
                                میانگین ساعت روزانه
                            </div>

                            <div class="mt-1 font-bold">
                                {{ number_format($staffSummary['average_hours_per_working_day'] ?? 0, 2) }}
                                ساعت
                            </div>

                        </div>

                    </div>

                </div>

            </x-filament::section>

        @endif


        {{-- ========================================================= --}}
        {{-- Today --}}
        {{-- ========================================================= --}}

        <div>

            <h2
                class="
                    mb-4
                    text-lg
                    font-bold
                    text-gray-950
                    dark:text-white
                    sm:text-xl
                "
            >
                گزارش امروز
            </h2>

            <div
                class="
                    grid
                    grid-cols-1
                    gap-3
                    sm:grid-cols-2
                    xl:grid-cols-4
                "
            >

                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        درآمد امروز
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($today['income'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        هزینه امروز
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($today['expense'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        برگشت وجه امروز
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($today['refund'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        درآمد خالص امروز
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($today['net_revenue'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- Current Month --}}
        {{-- ========================================================= --}}

        <div>

            <h2
                class="
                    mb-4
                    text-lg
                    font-bold
                    text-gray-950
                    dark:text-white
                    sm:text-xl
                "
            >
                گزارش ماه جاری
            </h2>

            <div
                class="
                    grid
                    grid-cols-1
                    gap-3
                    sm:grid-cols-2
                    xl:grid-cols-4
                "
            >

                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        درآمد ماه
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($month['income'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        هزینه ماه
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($month['expense'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        برگشت وجه ماه
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($month['refund'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>


                <x-filament::section>

                    <div class="text-sm text-gray-500">
                        درآمد خالص ماه
                    </div>

                    <div class="mt-2 break-words text-xl font-bold sm:text-2xl">
                        {{ number_format($month['net_revenue'] ?? 0) }}

                        <span class="text-xs font-normal sm:text-sm">
                            تومان
                        </span>
                    </div>

                </x-filament::section>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- Payment Methods --}}
        {{-- ========================================================= --}}

        <x-filament::section>

            <x-slot name="heading">
                روش‌های پرداخت ماه جاری
            </x-slot>

            <div
                class="
                    grid
                    grid-cols-1
                    gap-3
                    sm:grid-cols-2
                    lg:grid-cols-3
                    xl:grid-cols-5
                "
            >

                @php
                    $paymentMethods = [
                        'online' => 'آنلاین',
                        'cash' => 'نقدی',
                        'pos' => 'کارتخوان',
                        'bank_transfer' => 'انتقال بانکی',
                        'other' => 'سایر',
                    ];
                @endphp

                @foreach($paymentMethods as $key => $label)

                    <div
                        class="
                            rounded-xl
                            bg-gray-50
                            p-4
                            dark:bg-gray-800
                        "
                    >

                        <div class="text-sm text-gray-500">
                            {{ $label }}
                        </div>

                        <div class="mt-1 break-words font-bold">
                            {{ number_format($month['payment_methods'][$key] ?? 0) }}
                            تومان
                        </div>

                    </div>

                @endforeach

            </div>

        </x-filament::section>


        {{-- ========================================================= --}}
        {{-- Staff Current Month --}}
        {{-- ========================================================= --}}

        <x-filament::section>

            <x-slot name="heading">
                عملکرد پرسنل در ماه جاری
            </x-slot>

            <div
                class="
                    w-full
                    overflow-x-auto
                    rounded-xl
                    border
                    border-gray-200
                    dark:border-gray-700
                "
            >

                <table class="min-w-[750px] w-full text-sm">

                    <thead class="bg-gray-50 dark:bg-gray-800">

                    <tr>

                        <th class="whitespace-nowrap p-3 text-right">
                            پرسنل
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            رزروها
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            خدمات
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            مبلغ خدمات
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            ساعت خدمات
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    @forelse($staff as $row)

                        <tr
                            class="
                                    border-t
                                    border-gray-200
                                    dark:border-gray-700
                                "
                        >

                            <td class="whitespace-nowrap p-3 font-medium">
                                {{ $row['staff_name'] ?? '-' }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['bookings_count'] ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['services_count'] ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['services_amount'] ?? 0) }}
                                تومان
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['total_hours'] ?? 0, 2) }}
                                ساعت
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="p-6 text-center text-gray-500"
                            >
                                اطلاعاتی برای ماه جاری وجود ندارد.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </x-filament::section>


        {{-- ========================================================= --}}
        {{-- Services Current Month --}}
        {{-- ========================================================= --}}

        <x-filament::section>

            <x-slot name="heading">
                عملکرد خدمات در ماه جاری
            </x-slot>

            <div
                class="
                    w-full
                    overflow-x-auto
                    rounded-xl
                    border
                    border-gray-200
                    dark:border-gray-700
                "
            >

                <table class="min-w-[750px] w-full text-sm">

                    <thead class="bg-gray-50 dark:bg-gray-800">

                    <tr>

                        <th class="whitespace-nowrap p-3 text-right">
                            خدمت
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            رزروها
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            تعداد خدمات
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            مبلغ
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            مجموع ساعت
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    @forelse($services as $row)

                        <tr
                            class="
                                    border-t
                                    border-gray-200
                                    dark:border-gray-700
                                "
                        >

                            <td class="p-3 font-medium">
                                {{ $row['service_name'] ?? '-' }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['bookings_count'] ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['services_count'] ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['services_amount'] ?? 0) }}
                                تومان
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['total_hours'] ?? 0, 2) }}
                                ساعت
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="p-6 text-center text-gray-500"
                            >
                                اطلاعاتی برای ماه جاری وجود ندارد.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </x-filament::section>


        {{-- ========================================================= --}}
        {{-- Outstanding Customers --}}
        {{-- ========================================================= --}}

        <x-filament::section>

            <x-slot name="heading">
                مشتریان بدهکار
            </x-slot>

            <div
                class="
                    w-full
                    overflow-x-auto
                    rounded-xl
                    border
                    border-gray-200
                    dark:border-gray-700
                "
            >

                <table class="min-w-[900px] w-full text-sm">

                    <thead class="bg-gray-50 dark:bg-gray-800">

                    <tr>

                        <th class="whitespace-nowrap p-3 text-right">
                            مشتری
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            تلفن
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            رزروها
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            مبلغ کل
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            پرداخت خالص
                        </th>

                        <th class="whitespace-nowrap p-3 text-right">
                            بدهی
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    @forelse($outstandingCustomers as $row)

                        <tr
                            class="
                                    border-t
                                    border-gray-200
                                    dark:border-gray-700
                                "
                        >

                            <td class="whitespace-nowrap p-3 font-medium">
                                {{ $row['client_name'] ?? '-' }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ $row['client_phone'] ?? '-' }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['bookings_count'] ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['total_amount'] ?? 0) }}
                                تومان
                            </td>

                            <td class="whitespace-nowrap p-3">
                                {{ number_format($row['net_paid_amount'] ?? 0) }}
                                تومان
                            </td>

                            <td
                                class="
                                        whitespace-nowrap
                                        p-3
                                        font-bold
                                        text-danger-600
                                    "
                            >
                                {{ number_format($row['outstanding_amount'] ?? 0) }}
                                تومان
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="p-6 text-center text-gray-500"
                            >
                                مشتری بدهکاری وجود ندارد.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </x-filament::section>

    </div>

</x-filament-panels::page>
