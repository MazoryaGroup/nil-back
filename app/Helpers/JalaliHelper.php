<?php

namespace App\Helpers;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class JalaliHelper
{
    /**
     * Gregorian date -> Jalali date
     *
     * Example:
     * 2026-10-06 -> 1405/07/14
     */
    public static function date(
        string|Carbon|null $date,
        string $format = 'Y/m/d'
    ): ?string {
        if (empty($date)) {
            return null;
        }

        try {
            $carbon = $date instanceof Carbon
                ? $date
                : Carbon::parse($date);

            return Jalalian::fromCarbon($carbon)
                ->format($format);

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Gregorian datetime -> Jalali datetime
     *
     * Example:
     * 2026-10-06 15:30 -> 1405/07/14 15:30
     */
    public static function dateTime(
        string|Carbon|null $date,
        string $format = 'Y/m/d H:i'
    ): ?string {
        return self::date(
            $date,
            $format
        );
    }

    /**
     * Jalali date -> Gregorian date
     *
     * Example:
     * 1405/07/14 -> 2026-10-06
     */
    public static function toGregorian(
        ?string $jalaliDate,
        string $format = 'Y-m-d'
    ): ?string {
        if (empty($jalaliDate)) {
            return null;
        }

        try {
            $jalaliDate = str_replace(
                '-',
                '/',
                $jalaliDate
            );

            [$year, $month, $day] =
                array_map(
                    'intval',
                    explode('/', $jalaliDate)
                );

            return Jalalian::fromFormat(
                'Y/m/d',
                sprintf(
                    '%04d/%02d/%02d',
                    $year,
                    $month,
                    $day
                )
            )
                ->toCarbon()
                ->format($format);

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Current Jalali date
     */
    public static function now(
        string $format = 'Y/m/d'
    ): string {
        return Jalalian::now()
            ->format($format);
    }

    /**
     * Current Jalali datetime
     */
    public static function nowDateTime(
        string $format = 'Y/m/d H:i'
    ): string {
        return Jalalian::now()
            ->format($format);
    }
}
