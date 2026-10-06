<?php

namespace App\Exports;

use App\Services\AccountingReportService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AccountingReportExport implements WithMultipleSheets
{
    public function __construct(
        protected string $from,
        protected string $to,
        protected ?int $staffId = null,
        protected ?int $serviceId = null
    ) {
    }

    public function sheets(): array
    {
        $reportService = app(
            AccountingReportService::class
        );

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $summary = $reportService->summary(
            $this->from,
            $this->to
        );

        /*
        |--------------------------------------------------------------------------
        | Daily Report
        |--------------------------------------------------------------------------
        */

        $daily = $reportService->dailyReport(
            $this->from,
            $this->to
        );

        /*
        |--------------------------------------------------------------------------
        | Staff Report
        |--------------------------------------------------------------------------
        */

        $staff = $reportService->staffReport(
            $this->from,
            $this->to,
            $this->staffId,
            $this->serviceId
        );

        /*
        |--------------------------------------------------------------------------
        | Services Report
        |--------------------------------------------------------------------------
        */

        $services = $reportService->serviceReport(
            $this->from,
            $this->to,
            $this->staffId,
            $this->serviceId
        );

        /*
        |--------------------------------------------------------------------------
        | Outstanding Customers
        |--------------------------------------------------------------------------
        */

        $outstandingCustomers =
            $reportService->outstandingCustomers();

        /*
        |--------------------------------------------------------------------------
        | Base Excel Sheets
        |--------------------------------------------------------------------------
        */

        $sheets = [

            new AccountingSummarySheet(
                $summary
            ),

            new AccountingDailySheet(
                $daily
            ),

            new AccountingStaffSheet(
                $staff
            ),

            new AccountingServicesSheet(
                $services
            ),

            new AccountingOutstandingSheet(
                $outstandingCustomers
            ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Detailed Staff Sheets
        |--------------------------------------------------------------------------
        |
        | فقط زمانی ساخته می‌شوند که یک پرسنل انتخاب شده باشد.
        |
        */

        if ($this->staffId !== null) {

            /*
            |--------------------------------------------------------------------------
            | Staff Detailed Report
            |--------------------------------------------------------------------------
            */

            $staffDetailedReport =
                $reportService->staffDetailedReport(
                    $this->staffId,
                    $this->from,
                    $this->to
                );

            /*
            |--------------------------------------------------------------------------
            | Staff Schedule Report
            |--------------------------------------------------------------------------
            */

            $staffScheduleReport =
                $reportService->staffScheduleReport(
                    $this->staffId,
                    $this->from,
                    $this->to
                );

            /*
            |--------------------------------------------------------------------------
            | Add Staff Detailed Sheet
            |--------------------------------------------------------------------------
            */

            $sheets[] =
                new AccountingStaffDetailedSheet(
                    $staffDetailedReport
                );

            /*
            |--------------------------------------------------------------------------
            | Add Staff Schedule Sheet
            |--------------------------------------------------------------------------
            */

            $sheets[] =
                new AccountingStaffScheduleSheet(
                    $staffScheduleReport
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Return Sheets
        |--------------------------------------------------------------------------
        */

        return $sheets;
    }
}
