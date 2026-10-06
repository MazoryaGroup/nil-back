<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AccountingServicesSheet implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(
        protected array $services
    ) {
    }

    public function headings(): array
    {
        return [
            'شناسه خدمت',
            'نام خدمت',
            'تعداد رزرو',
            'تعداد خدمات',
            'مبلغ خدمات',
            'مجموع دقیقه',
            'مجموع ساعت',
        ];
    }

    public function array(): array
    {
        return collect($this->services)
            ->map(function (array $row) {
                return [
                    $row['service_id'] ?? '-',
                    $row['service_name'] ?? '-',
                    (int) ($row['bookings_count'] ?? 0),
                    (int) ($row['services_count'] ?? 0),
                    (float) ($row['services_amount'] ?? 0),
                    (int) ($row['total_minutes'] ?? 0),
                    (float) ($row['total_hours'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function title(): string
    {
        return 'Services Report';
    }
}
