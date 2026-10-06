<?php

namespace App\Filament\Widgets;

use App\Models\CashRegister;
use App\Services\AccountingReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        /** @var AccountingReportService $reportService */
        $reportService = app(AccountingReportService::class);

        $today = $reportService->today();
        $month = $reportService->currentMonth();

        /*
        |--------------------------------------------------------------------------
        | Today's Cash Register
        |--------------------------------------------------------------------------
        */

        $cashRegister = CashRegister::query()
            ->whereDate('register_date', now()->toDateString())
            ->first();

        $cashBalance = (float) ($cashRegister?->closing_balance ?? 0);

        /*
        |--------------------------------------------------------------------------
        | Outstanding Customers
        |--------------------------------------------------------------------------
        */

        $outstandingCustomers = $reportService->outstandingCustomers();

        $totalOutstanding = collect($outstandingCustomers)
            ->sum('outstanding_amount');

        return [

            /*
            |--------------------------------------------------------------------------
            | Today
            |--------------------------------------------------------------------------
            */

            Stat::make(
                'درآمد امروز',
                $this->money($today['income'])
            )
                ->description('مجموع درآمد ثبت‌شده امروز')
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make(
                'خالص امروز',
                $this->money($today['net_revenue'])
            )
                ->description('درآمد منهای هزینه و برگشت وجه')
                ->descriptionIcon('heroicon-m-banknotes'),

            /*
            |--------------------------------------------------------------------------
            | Month
            |--------------------------------------------------------------------------
            */

            Stat::make(
                'درآمد این ماه',
                $this->money($month['income'])
            )
                ->description('مجموع درآمد ماه جاری')
                ->descriptionIcon('heroicon-m-chart-bar'),

            Stat::make(
                'خالص این ماه',
                $this->money($month['net_revenue'])
            )
                ->description('خالص عملکرد مالی ماه جاری')
                ->descriptionIcon('heroicon-m-currency-dollar'),

            Stat::make(
                'هزینه این ماه',
                $this->money($month['expense'])
            )
                ->description('مجموع هزینه‌های ماه جاری')
                ->descriptionIcon('heroicon-m-arrow-trending-down'),

            Stat::make(
                'برگشت وجه این ماه',
                $this->money($month['refund'])
            )
                ->description('مجموع برگشت وجه ماه جاری')
                ->descriptionIcon('heroicon-m-arrow-uturn-left'),

            /*
            |--------------------------------------------------------------------------
            | Cash Register
            |--------------------------------------------------------------------------
            */

            Stat::make(
                'موجودی صندوق امروز',
                $this->money($cashBalance)
            )
                ->description(
                    $cashRegister
                        ? ($cashRegister->status === 'open'
                        ? 'صندوق امروز باز است'
                        : 'صندوق امروز بسته شده')
                        : 'صندوق امروز هنوز ایجاد نشده'
                )
                ->descriptionIcon('heroicon-m-wallet'),

            /*
            |--------------------------------------------------------------------------
            | Outstanding
            |--------------------------------------------------------------------------
            */

            Stat::make(
                'کل مطالبات',
                $this->money($totalOutstanding)
            )
                ->description(
                    number_format(count($outstandingCustomers))
                    . ' مشتری دارای بدهی'
                )
                ->descriptionIcon('heroicon-m-exclamation-circle'),
        ];
    }

    private function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount) . ' تومان';
    }
}
