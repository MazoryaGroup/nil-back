<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AccountingStaffDetailedSheet implements
    FromCollection,
    WithHeadings,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(
        protected array $report
    ) {
    }

    public function collection(): Collection
    {
        $summary = $this->report['summary'] ?? [];

        return collect([
            [
                'روزهای دارای فعالیت',
                $summary['working_days_count'] ?? 0,
            ],
            [
                'روزهای مرخصی',
                $summary['leave_days_count'] ?? 0,
            ],
            [
                'تعداد رزرو',
                $summary['bookings_count'] ?? 0,
            ],
            [
                'تعداد مشتری',
                $summary['clients_count'] ?? 0,
            ],
            [
                'تعداد خدمات',
                $summary['services_count'] ?? 0,
            ],
            [
                'مبلغ خدمات',
                $summary['services_amount'] ?? 0,
            ],
            [
                'مجموع دقیقه خدمات',
                $summary['total_minutes'] ?? 0,
            ],
            [
                'مجموع ساعت خدمات',
                $summary['total_hours'] ?? 0,
            ],
            [
                'میانگین درآمد روز کاری',
                $summary['average_revenue_per_working_day'] ?? 0,
            ],
            [
                'میانگین رزرو در روز کاری',
                $summary['average_bookings_per_working_day'] ?? 0,
            ],
            [
                'میانگین خدمات در روز کاری',
                $summary['average_services_per_working_day'] ?? 0,
            ],
            [
                'میانگین ساعت خدمات در روز کاری',
                $summary['average_hours_per_working_day'] ?? 0,
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'عنوان',
            'مقدار',
        ];
    }

    public function title(): string
    {
        return 'Staff Detailed';
    }
}
