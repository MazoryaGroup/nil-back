<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AccountingOutstandingSheet implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(
        protected array $customers
    ) {
    }

    public function headings(): array
    {
        return [
            'شناسه مشتری',
            'نام مشتری',
            'شماره تماس',
            'تعداد رزرو',
            'مبلغ کل',
            'مبلغ پرداخت‌شده',
            'مبلغ بازپرداخت',
            'خالص پرداخت‌شده',
            'مانده بدهی',
        ];
    }

    public function array(): array
    {
        return collect($this->customers)
            ->map(function (array $row) {
                return [
                    $row['client_id'] ?? '-',
                    $row['client_name'] ?? '-',
                    $row['client_phone'] ?? '-',
                    (int) ($row['bookings_count'] ?? 0),
                    (float) ($row['total_amount'] ?? 0),
                    (float) ($row['paid_amount'] ?? 0),
                    (float) ($row['refund_amount'] ?? 0),
                    (float) ($row['net_paid_amount'] ?? 0),
                    (float) ($row['outstanding_amount'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function title(): string
    {
        return 'Outstanding';
    }
}
