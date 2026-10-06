<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Helpers\JalaliHelper;

class AccountingDailySheet implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(
        protected array $daily
    ) {
    }

    public function headings(): array
    {
        return [
            'تاریخ',
            'درآمد',
            'هزینه',
            'بازپرداخت',
            'درآمد خالص',
        ];
    }

    public function array(): array
    {
        return collect($this->daily)
            ->map(function (array $row) {
                return [
                    !empty($row['date'])
                        ? JalaliHelper::date($row['date'], 'Y/m/d')
                        : '-',
                    (float) ($row['income'] ?? 0),
                    (float) ($row['expense'] ?? 0),
                    (float) ($row['refund'] ?? 0),
                    (float) ($row['net_revenue'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function title(): string
    {
        return 'Daily Report';
    }
}
