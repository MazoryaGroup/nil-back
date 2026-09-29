<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('chat:close-inactive')->everyMinute();
        // هماهنگ‌سازی با گوگل هر 15 دقیقه یک‌بار به صورت خودکار
        $schedule->command('app:sync-google-calendar')->everyFifteenMinutes();

        // دستور یادآوری که قبلاً نوشتیم
        $schedule->command('app:send-daily-reminders')->dailyAt('22:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

}
