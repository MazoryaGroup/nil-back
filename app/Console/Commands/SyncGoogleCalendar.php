<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Spatie\GoogleCalendar\Event as GoogleEvent;
use Carbon\Carbon;

class SyncGoogleCalendar extends Command
{
    // این باعث می‌شود ویجت مثلاً فقط نصف صفحه را بگیرد (در سیستم ۱۲ ستونه فیلامنت)

    protected $signature = 'app:sync-google-calendar';
    protected $description = 'دریافت ایونت‌ها از گوگل و تنظیم یادآوری‌ها';

    public function handle()
    {
        try {
            // ۱. دریافت تمام ایونت‌های گوگل در بازه زمانی مشخص
            $googleEvents = GoogleEvent::get(now()->subMonth(), now()->addMonths(3));

            // ۲. استخراج تمام آی‌دی‌های گوگل که همین الان فعال هستند
            $activeGoogleIds = $googleEvents->pluck('id')->toArray();

            // ۳. حذف ایونت‌هایی از دیتابیس داخلی که در گوگل دیگر وجود ندارند
            // فقط آنهایی را پاک می‌کنیم که آی‌دی گوگل دارند ولی در لیست جدید نیستند
            Event::whereNotNull('google_event_id')
                ->whereNotIn('google_event_id', $activeGoogleIds)
                ->delete(); // چون در دستور کنسول هستیم، این حذف به گوگل سیگنال برنمی‌گرداند (طبق شرط مدل)

            foreach ($googleEvents as $gEvent) {
                $eventData = [
                    'title'       => $gEvent->name ?? $gEvent->summary ?? 'بدون نام',
                    'start_date'  => Carbon::parse($gEvent->startDateTime ?? $gEvent->startDate),
                    'end_date'    => Carbon::parse($gEvent->endDateTime ?? $gEvent->endDate),
                    'description' => $gEvent->description ?? '',
                    'google_event_id' => $gEvent->id,
                ];

                $localEvent = Event::where('google_event_id', $gEvent->id)->first();

                if ($localEvent) {
                    // آپدیت بدون بیدار کردن متدهای مدل
                    $localEvent->forceFill($eventData)->saveQuietly();
                } else {
                    // ایجاد جدید بدون بیدار کردن متدهای مدل
                    $newEvent = new Event($eventData);
                    $newEvent->saveQuietly();
                }
            }

            $this->info('همگام‌سازی کامل (شامل حذف موارد منقضی) انجام شد.');
        } catch (\Exception $e) {
            $this->error('خطا: ' . $e->getMessage());
        }
    }
    protected function updateGoogleReminders($gEvent)
    {
        // اگر از قبل یادآوری اختصاصی دارد، دوباره ذخیره نکن تا وب‌هوک تکراری نیاید
        if (isset($gEvent->reminders->overrides) && count($gEvent->reminders->overrides) > 0) {
            return;
        }

        $gEvent->reminders = [
            'useDefault' => false,
            'overrides' => [
                ['method' => 'email', 'minutes' => 1440],
                ['method' => 'popup', 'minutes' => 60],
            ],
        ];

        $gEvent->save();
    }
}
