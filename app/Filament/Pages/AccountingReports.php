<?php

namespace App\Filament\Pages;

use App\Exports\AccountingReportExport;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AccountingReportService;

use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDatePicker;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

class AccountingReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon =
        'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel =
        'گزارش‌های حسابداری';

    protected static ?string $title =
        'گزارش‌های حسابداری';

    protected static ?string $navigationGroup =
        'حسابداری';

    protected static ?int $navigationSort = 8;

    protected static string $view =
        'filament.pages.accounting-reports';

    /*
    |--------------------------------------------------------------------------
    | Default Reports
    |--------------------------------------------------------------------------
    */

    public array $today = [];

    public array $month = [];

    public array $staff = [];

    public array $services = [];

    public array $outstandingCustomers = [];

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?int $selectedStaffId = null;

    public ?int $selectedServiceId = null;

    /*
    |--------------------------------------------------------------------------
    | Filter Options
    |--------------------------------------------------------------------------
    */

    public array $staffOptions = [];

    public array $serviceOptions = [];

    /*
    |--------------------------------------------------------------------------
    | Custom Reports
    |--------------------------------------------------------------------------
    */

    public array $customSummary = [];

    public array $customDaily = [];

    public array $customStaff = [];

    public array $customServices = [];

    public array $staffDetailedReport = [];

    public array $staffScheduleReport = [];

    /*
    |--------------------------------------------------------------------------
    | Filament Form
    |--------------------------------------------------------------------------
    */

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                JalaliDatePicker::make('fromDate')
                    ->label('از تاریخ')
                    ->required()
                    ->displayFormat('Y/m/d')
                    ->native(false),

                JalaliDatePicker::make('toDate')
                    ->label('تا تاریخ')
                    ->required()
                    ->displayFormat('Y/m/d')
                    ->native(false),

                Select::make('selectedStaffId')
                    ->label('پرسنل')
                    ->options(
                        fn (): array =>
                        $this->staffOptions
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('همه پرسنل'),

                Select::make('selectedServiceId')
                    ->label('خدمت')
                    ->options(
                        fn (): array =>
                        $this->serviceOptions
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('همه خدمات'),
            ])
            ->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Mount
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $reportService = app(
            AccountingReportService::class
        );

        /*
        |--------------------------------------------------------------------------
        | Staff Options
        |--------------------------------------------------------------------------
        */

        $this->staffOptions = Staff::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn ($name, $id) => [
                    (int) $id => (string) $name,
                ]
            )
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Service Options
        |--------------------------------------------------------------------------
        */

        $this->serviceOptions = Service::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn ($name, $id) => [
                    (int) $id => (string) $name,
                ]
            )
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Default Reports
        |--------------------------------------------------------------------------
        */

        $this->today =
            $reportService->today();

        $this->month =
            $reportService->currentMonth();

        $this->staff =
            $reportService->currentMonthStaff();

        $this->services =
            $reportService->currentMonthServices();

        $this->outstandingCustomers =
            $reportService->outstandingCustomers();

        /*
        |--------------------------------------------------------------------------
        | Default Date Range
        |--------------------------------------------------------------------------
        */

        $this->fromDate = now()
            ->startOfMonth()
            ->toDateString();

        $this->toDate = now()
            ->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Fill Filament Form
        |--------------------------------------------------------------------------
        */

        $this->form->fill([
            'fromDate' =>
                $this->fromDate,

            'toDate' =>
                $this->toDate,

            'selectedStaffId' =>
                $this->selectedStaffId,

            'selectedServiceId' =>
                $this->selectedServiceId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Load Initial Custom Report
        |--------------------------------------------------------------------------
        */

        $this->loadCustomReport();
    }

    /*
    |--------------------------------------------------------------------------
    | Load Custom Report
    |--------------------------------------------------------------------------
    */

    public function loadCustomReport(): void
    {
        $this->validate([
            'fromDate' => [
                'required',
                'date',
            ],

            'toDate' => [
                'required',
                'date',
                'after_or_equal:fromDate',
            ],

            'selectedStaffId' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],

            'selectedServiceId' => [
                'nullable',
                'integer',
                'exists:services,id',
            ],
        ]);

        $reportService = app(
            AccountingReportService::class
        );

        /*
        |--------------------------------------------------------------------------
        | Financial Summary
        |--------------------------------------------------------------------------
        */

        $this->customSummary =
            $reportService->summary(
                $this->fromDate,
                $this->toDate
            );

        /*
        |--------------------------------------------------------------------------
        | Daily Report
        |--------------------------------------------------------------------------
        */

        $this->customDaily =
            $reportService->dailyReport(
                $this->fromDate,
                $this->toDate
            );

        /*
        |--------------------------------------------------------------------------
        | Staff Report
        |--------------------------------------------------------------------------
        */

        $this->customStaff =
            $reportService->staffReport(
                $this->fromDate,
                $this->toDate,
                $this->selectedStaffId,
                $this->selectedServiceId
            );

        /*
        |--------------------------------------------------------------------------
        | Services Report
        |--------------------------------------------------------------------------
        */

        $this->customServices =
            $reportService->serviceReport(
                $this->fromDate,
                $this->toDate,
                $this->selectedStaffId,
                $this->selectedServiceId
            );

        /*
        |--------------------------------------------------------------------------
        | Detailed Staff Report
        |--------------------------------------------------------------------------
        */

        $this->staffDetailedReport = [];

        $this->staffScheduleReport = [];

        if ($this->selectedStaffId !== null) {

            $this->staffDetailedReport =
                $reportService->staffDetailedReport(
                    $this->selectedStaffId,
                    $this->fromDate,
                    $this->toDate
                );

            $this->staffScheduleReport =
                $reportService->staffScheduleReport(
                    $this->selectedStaffId,
                    $this->fromDate,
                    $this->toDate
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Export Excel
    |--------------------------------------------------------------------------
    */

    public function exportExcel()
    {
        $this->validate([
            'fromDate' => [
                'required',
                'date',
            ],

            'toDate' => [
                'required',
                'date',
                'after_or_equal:fromDate',
            ],

            'selectedStaffId' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],

            'selectedServiceId' => [
                'nullable',
                'integer',
                'exists:services,id',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | File Name
        |--------------------------------------------------------------------------
        */

        $fileName =
            'NIL-Accounting-Report-' .
            $this->fromDate .
            '-to-' .
            $this->toDate .
            '.xlsx';

        /*
        |--------------------------------------------------------------------------
        | Download
        |--------------------------------------------------------------------------
        */

        return Excel::download(
            new AccountingReportExport(
                $this->fromDate,
                $this->toDate,
                $this->selectedStaffId,
                $this->selectedServiceId
            ),
            $fileName
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Export PDF
    |--------------------------------------------------------------------------
    */

    public function exportPdf()
    {
        $this->validate([
            'fromDate' => [
                'required',
                'date',
            ],

            'toDate' => [
                'required',
                'date',
                'after_or_equal:fromDate',
            ],

            'selectedStaffId' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],

            'selectedServiceId' => [
                'nullable',
                'integer',
                'exists:services,id',
            ],
        ]);

        $reportService = app(
            AccountingReportService::class
        );

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $summary =
            $reportService->summary(
                $this->fromDate,
                $this->toDate
            );

        /*
        |--------------------------------------------------------------------------
        | Staff Report
        |--------------------------------------------------------------------------
        */

        $staff =
            $reportService->staffReport(
                $this->fromDate,
                $this->toDate,
                $this->selectedStaffId,
                $this->selectedServiceId
            );

        /*
        |--------------------------------------------------------------------------
        | Services Report
        |--------------------------------------------------------------------------
        */

        $services =
            $reportService->serviceReport(
                $this->fromDate,
                $this->toDate,
                $this->selectedStaffId,
                $this->selectedServiceId
            );

        /*
        |--------------------------------------------------------------------------
        | Selected Staff
        |--------------------------------------------------------------------------
        */

        $selectedStaff = null;

        if ($this->selectedStaffId !== null) {

            $selectedStaff = Staff::find(
                $this->selectedStaffId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Selected Service
        |--------------------------------------------------------------------------
        */

        $selectedService = null;

        if ($this->selectedServiceId !== null) {

            $selectedService = Service::find(
                $this->selectedServiceId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Detailed Staff Reports
        |--------------------------------------------------------------------------
        */

        $staffDetailedReport = [];

        $staffScheduleReport = [];

        if ($this->selectedStaffId !== null) {

            /*
            | Performance:
            | bookings, clients, services, revenue,
            | working dates, future bookings, service breakdown...
            */

            $staffDetailedReport =
                $reportService->staffDetailedReport(
                    $this->selectedStaffId,
                    $this->fromDate,
                    $this->toDate
                );

            /*
            | Schedule:
            | schedules, breaks, leaves, available time,
            | worked time, utilization, outside schedule...
            */

            $staffScheduleReport =
                $reportService->staffScheduleReport(
                    $this->selectedStaffId,
                    $this->fromDate,
                    $this->toDate
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Render HTML
        |--------------------------------------------------------------------------
        */

        $html = view(
            'pdf.accounting-report',
            [
                'from' =>
                    $this->fromDate,

                'to' =>
                    $this->toDate,

                'summary' =>
                    $summary,

                'staff' =>
                    $staff,

                'services' =>
                    $services,

                'selectedStaff' =>
                    $selectedStaff,

                'selectedService' =>
                    $selectedService,

                'staffDetailedReport' =>
                    $staffDetailedReport,

                'staffScheduleReport' =>
                    $staffScheduleReport,
            ]
        )->render();

        /*
        |--------------------------------------------------------------------------
        | Create PDF
        |--------------------------------------------------------------------------
        */

        $mpdf = new Mpdf([
            'mode' =>
                'utf-8',

            'format' =>
                'A4',

            'orientation' =>
                'P',

            'directionality' =>
                'rtl',

            'autoScriptToLang' =>
                true,

            'autoLangToFont' =>
                true,

            'margin_top' =>
                15,

            'margin_right' =>
                15,

            'margin_bottom' =>
                15,

            'margin_left' =>
                15,
        ]);

        $mpdf->SetDirectionality(
            'rtl'
        );

        $mpdf->WriteHTML(
            $html
        );

        /*
        |--------------------------------------------------------------------------
        | File Name
        |--------------------------------------------------------------------------
        */

        $fileName =
            'NIL-Accounting-Report-' .
            $this->fromDate .
            '-to-' .
            $this->toDate;

        /*
        |--------------------------------------------------------------------------
        | Add Staff To File Name
        |--------------------------------------------------------------------------
        */

        if ($selectedStaff) {

            $fileName .=
                '-staff-' .
                $selectedStaff->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Add Service To File Name
        |--------------------------------------------------------------------------
        */

        if ($selectedService) {

            $fileName .=
                '-service-' .
                $selectedService->id;
        }

        $fileName .= '.pdf';

        /*
        |--------------------------------------------------------------------------
        | PDF Content
        |--------------------------------------------------------------------------
        */

        $pdfContent =
            $mpdf->Output(
                '',
                'S'
            );

        /*
        |--------------------------------------------------------------------------
        | Download
        |--------------------------------------------------------------------------
        */

        return response()->streamDownload(
            function () use ($pdfContent) {

                echo $pdfContent;
            },
            $fileName,
            [
                'Content-Type' =>
                    'application/pdf',
            ]
        );
    }
}
