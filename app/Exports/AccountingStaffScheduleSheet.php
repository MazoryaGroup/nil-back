<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Helpers\JalaliHelper;

class AccountingStaffScheduleSheet implements
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
        $days = $this->report['days'] ?? [];

        return collect($days)
            ->map(function (array $day) {

                return [
                    'date' => !empty($day['date'])
                        ? JalaliHelper::date($day['date'], 'Y/m/d')
                        : '-',

                    'status' =>
                        $this->statusLabel(
                            $day['status'] ?? null
                        ),

                    'scheduled_hours' =>
                        $day['scheduled_hours'] ?? 0,

                    'break_hours' =>
                        $day['break_hours'] ?? 0,

                    'leave_hours' =>
                        $day['leave_hours'] ?? 0,

                    'available_hours' =>
                        $day['available_hours'] ?? 0,

                    'worked_hours' =>
                        $day['worked_hours'] ?? 0,

                    'raw_worked_hours' =>
                        $day['raw_worked_hours'] ?? 0,

                    'outside_schedule_worked_hours' =>
                        $day['outside_schedule_worked_hours'] ?? 0,

                    'service_workload_hours' =>
                        $day['service_workload_hours'] ?? 0,

                    'empty_hours' =>
                        $day['empty_hours'] ?? 0,

                    'utilization_percent' =>
                        $day['utilization_percent'] ?? 0,
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'تاریخ',
            'وضعیت',
            'ساعت برنامه',
            'استراحت',
            'مرخصی',
            'زمان قابل کار',
            'کارکرد واقعی',
            'کارکرد خام',
            'کار خارج از زمان قابل کار',
            'بار کاری خدمات',
            'زمان خالی',
            'بهره‌وری %',
        ];
    }

    public function title(): string
    {
        return 'Staff Schedule';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {

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

            'off' =>
            'تعطیل',

            default =>
                $status ?? '-',
        };
    }
}
