<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat; // تغییر از Card به Stat
use App\Models\Client;
use App\Models\Conversation;
use App\Models\WaitingList;

class PanelUsers extends BaseWidget
{
    protected static ?int $sort = 2; // عدد کمتر = اولویت بالاتر (بالاتر نمایش داده می‌شود)
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array // تغییر نام متد از getCards به getStats
    {
        return [
            Stat::make('تعداد کاربران', Client::count())
                ->description('کل کاربران ثبت‌نام‌شده')
                ->descriptionIcon('heroicon-m-users') // آیکون کوچک نسخه ۳
                ->color('primary'),




        ];
    }
}
