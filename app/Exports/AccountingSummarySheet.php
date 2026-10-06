<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Helpers\JalaliHelper;

class AccountingSummarySheet implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(
        protected array $summary
    ) {
    }

    public function headings(): array
    {
        return [
            'عنوان',
            'مقدار',
        ];
    }

    public function array(): array
    {
        $paymentMethods = $this->summary['payment_methods'] ?? [];

        return [
            [
                'از تاریخ',
                !empty($this->summary['from'])
                    ? JalaliHelper::date($this->summary['from'], 'Y/m/d')
                    : '-',
            ],
            [
                'تا تاریخ',
                !empty($this->summary['to'])
                    ? JalaliHelper::date($this->summary['to'], 'Y/m/d')
                    : '-',
            ],

            ['درآمد', (float) ($this->summary['income'] ?? 0)],
            ['هزینه', (float) ($this->summary['expense'] ?? 0)],
            ['بازپرداخت', (float) ($this->summary['refund'] ?? 0)],
            ['درآمد خالص', (float) ($this->summary['net_revenue'] ?? 0)],

            ['پرداخت آنلاین', (float) ($paymentMethods['online'] ?? 0)],
            ['پرداخت نقدی', (float) ($paymentMethods['cash'] ?? 0)],
            ['کارتخوان', (float) ($paymentMethods['pos'] ?? 0)],
            ['انتقال بانکی', (float) ($paymentMethods['bank_transfer'] ?? 0)],
            ['سایر', (float) ($paymentMethods['other'] ?? 0)],
        ];
    }

    public function title(): string
    {
        return 'Summary';
    }
}
