<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Service\Calendar\Channel;

class WatchGoogleCalendar extends Command
{
    protected $signature = 'app:watch-google-calendar';
    protected $description = 'فعال‌سازی اطلاع‌رسانی آنی گوگل کلندر';

    public function handle()
    {
        try {
            $calendarId = config('google-calendar.calendar_id');
            $webhookUrl = 'https://api.cardifygroup.com/google-calendar/webhook';
            $id = 'watch-channel-' . time();

            // ۱. ساخت کلاینت گوگل به صورت مستقیم
            $client = new \Google\Client();
            $client->setAuthConfig(config('google-calendar.auth_profiles.service_account.credentials_json'));
            $client->addScope(\Google\Service\Calendar::CALENDAR);

            $googleService = new \Google\Service\Calendar($client);

            // ۲. تنظیم کانال وب‌هوک
            $channel = new \Google\Service\Calendar\Channel();
            $channel->setId($id);
            $channel->setType('web_hook');
            $channel->setAddress($webhookUrl);

            // ۳. انقضای کانال (مثلاً برای ۳۰ روز آینده - گوگل معمولاً کمتر تایید می‌کند اما ما حداکثر را می‌زنیم)
            // $channel->setExpiration(now()->addDays(30)->timestamp * 1000);

            // ۴. ارسال درخواست به گوگل
            $googleService->events->watch($calendarId, $channel);

            $this->info("نظارت گوگل با موفقیت فعال شد.");
            $this->info("آدرس مقصد: $webhookUrl");
            $this->info("آی‌دی کانال: $id");

        } catch (\Exception $e) {
            $this->error("خطا در فعال‌سازی: " . $e->getMessage());
        }
    }
}
